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
        'security.login_failures_per_minute' => 100,
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

it('caps failed login attempts on the portal, from any network', function () {
    config(['security.login_failures_per_minute' => 3]);
    User::factory()->create(['email' => 'member@example.test']);

    loginFrom('198.51.100.2', 'a@example.test')->assertStatus(422);
    loginFrom('192.0.2.3', 'b@example.test')->assertStatus(422);
    loginFrom('203.0.113.1', 'c@example.test')->assertStatus(422);

    Hash::spy();

    loginFrom('203.0.113.4', 'member@example.test', 'password')->assertStatus(429);

    Hash::shouldNotHaveReceived('check');
});

it('never counts successful logins toward the portal cap, so a valid account cannot lock others out', function () {
    config(['security.login_failures_per_minute' => 3]);
    User::factory()->create(['email' => 'attacker@example.test']);
    User::factory()->create(['email' => 'member@example.test']);

    // صاحب حساب صحيح (ولو بتحقق ثنائي) يكرر الدخول من شبكات كثيرة
    foreach (range(1, 10) as $i) {
        loginFrom("198.51.100.{$i}", 'attacker@example.test', 'password')->assertOk();
        $this->post('/logout');
    }

    loginFrom('203.0.113.9', 'member@example.test', 'password')->assertOk();
});

it('limits all attempts from one network, successful ones included', function () {
    config(['security.login_attempts_per_subscriber_per_minute' => 3]);
    User::factory()->create(['email' => 'member@example.test']);

    foreach (range(1, 3) as $i) {
        loginFrom('203.0.113.7', 'member@example.test', 'password')->assertOk();
        $this->post('/logout');
    }

    // شبكة أخرى لا تتأثر
    loginFrom('198.51.100.7', 'member@example.test', 'password')->assertOk();
    $this->post('/logout');

    Hash::spy();

    loginFrom('203.0.113.7', 'member@example.test', 'password')->assertStatus(429);

    Hash::shouldNotHaveReceived('check');
    $this->assertGuest();
});

it('turns the portal cap and the network attempts limit off at zero', function () {
    config(['security.login_failures_per_minute' => 0, 'security.login_attempts_per_subscriber_per_minute' => 0]);

    foreach (range(1, 5) as $i) {
        loginFrom("198.51.100.{$i}", "user{$i}@example.test")->assertStatus(422);
    }
});
