<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

/**
 * حدود الدخول لكل شبكة وللبوابة كلها (الفحص الأمني 14 أكتوبر — البند 2): تخمين كلمات المرور
 * على حسابات كثيرة من مصدر واحد يُحجب، والحجب قبل فحص كلمة المرور فلا يُنهك المعالج.
 */
function loginFrom(string $ip, string $login, string $password = 'wrong-password'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/login', ['login' => $login, 'password' => $password]);
}

beforeEach(function () {
    config([
        'security.login_failures_per_subscriber' => 3,
        'security.login_failures_per_ipv6_site' => 6,
        'security.login_attempts_per_minute' => 100,
    ]);
});

it('blocks one network guessing passwords across different accounts', function () {
    foreach (range(1, 3) as $i) {
        loginFrom('203.0.113.7', "user{$i}@example.test")->assertStatus(422);
    }

    loginFrom('203.0.113.7', 'user4@example.test')->assertStatus(429)->assertJsonValidationErrors('login');

    // شبكة أخرى لا تتأثر
    loginFrom('198.51.100.7', 'user4@example.test')->assertStatus(422);
});

it('rejects a blocked network before checking any password', function () {
    $user = User::factory()->create(['email' => 'member@example.test']);

    foreach (range(1, 3) as $i) {
        loginFrom('203.0.113.7', "user{$i}@example.test");
    }

    Hash::spy();

    loginFrom('203.0.113.7', 'member@example.test', 'password')->assertStatus(429);

    Hash::shouldNotHaveReceived('check');
    $this->assertGuest();
    expect($user->fresh()->last_login_at)->toBeNull();
});

it('treats a whole IPv6 /64 as one subscriber, and a /48 as one site', function () {
    foreach (range(1, 3) as $i) {
        loginFrom("2001:db8:1:1::{$i}", "user{$i}@example.test")->assertStatus(422);
    }

    loginFrom('2001:db8:1:1::99', 'other@example.test')->assertStatus(429);

    // تدوير الشبكات الفرعية داخل الموقع نفسه يبلغ حد الموقع
    foreach (range(1, 3) as $i) {
        loginFrom("2001:db8:1:{$i}0::1", "site{$i}@example.test")->assertStatus(422);
    }

    loginFrom('2001:db8:1:ff::1', 'site9@example.test')->assertStatus(429);
});

it('does not reset the network counter on a successful login', function () {
    User::factory()->create(['email' => 'member@example.test']);

    foreach (range(1, 2) as $i) {
        loginFrom('203.0.113.7', "user{$i}@example.test");
    }

    loginFrom('203.0.113.7', 'member@example.test', 'password')->assertOk();
    $this->post('/logout');

    loginFrom('203.0.113.7', 'user3@example.test')->assertStatus(422);
    loginFrom('203.0.113.7', 'user4@example.test')->assertStatus(429);
});

it('caps all login attempts on the portal, successful ones included', function () {
    config(['security.login_attempts_per_minute' => 3]);
    User::factory()->create(['email' => 'member@example.test']);

    loginFrom('203.0.113.1', 'member@example.test', 'password')->assertOk();
    $this->post('/logout');
    loginFrom('198.51.100.2', 'a@example.test')->assertStatus(422);
    loginFrom('192.0.2.3', 'b@example.test')->assertStatus(422);

    Hash::spy();

    loginFrom('203.0.113.4', 'member@example.test', 'password')->assertStatus(429);

    Hash::shouldNotHaveReceived('check');
});

it('turns the portal cap off at zero', function () {
    config(['security.login_attempts_per_minute' => 0]);

    foreach (range(1, 5) as $i) {
        loginFrom("198.51.100.{$i}", "user{$i}@example.test")->assertStatus(422);
    }
});
