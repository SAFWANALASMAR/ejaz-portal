<?php

use App\Contracts\BotChallenge;
use App\Models\MessagingChannel;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\LoginChallengeOnAccount;
use App\Services\BotChallenge\FakeBotChallenge;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * التحدي بدل الحجب في الدخول (قرار صفوان، 27 أكتوبر 2026 — م١ وم٢ في مراجعة 0.14 السحابية): متى ضُبط Turnstile
 * فمن اجتازه لا تحجبه حدود الحساب والشبكة، بل سقوفها الصارمة. وبلا مفتاحيه يبقى الحجب كما كان.
 */
function challengedLogin(string $ip, string $login, string $password = 'wrong-password'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/login', ['login' => $login, 'password' => $password, 'cf-turnstile-response' => 'token']);
}

function withTurnstileKeys(bool $passes = true): void
{
    app()->instance(BotChallenge::class, new FakeBotChallenge(passes: $passes, siteKey: 'site-key-test'));
}

beforeEach(function () {
    Notification::fake();

    config([
        'security.login_failures_per_subscriber' => 3,
        'security.login_failures_per_ipv6_site' => 6,
        'security.login_failures_per_minute' => 1000,
        'security.login_challenge_max_per_subscriber' => 40,
        'security.login_challenge_max_per_ipv6_site' => 80,
        'security.login_challenge_max_per_account' => 15,
        'security.login_challenge_max_per_identity_ip_per_minute' => 15,
    ]);
});

it('no longer lets one student lock the whole school out behind one address once Turnstile is set', function () {
    withTurnstileKeys();
    $classmate = User::factory()->create(['email' => 'classmate@example.test']);

    foreach (range(1, 5) as $i) {
        challengedLogin('203.0.113.7', "random{$i}@example.test")->assertStatus(422);
    }

    challengedLogin('203.0.113.7', 'classmate@example.test', 'password')->assertOk();
    $this->assertAuthenticatedAs($classmate);
});

it('no longer lets a classmate keep a rival out of their account once Turnstile is set', function () {
    withTurnstileKeys();
    $rival = User::factory()->create(['email' => 'rival@example.test', 'username' => 'rival.student']);

    // بالمعرّفين ومن عنوان المدرسة نفسه: يتجاوز حد المعرّف والعنوان في الدقيقة، وحد المعرّف، وحد الحساب
    foreach (range(1, 6) as $i) {
        challengedLogin('203.0.113.7', 'rival@example.test')->assertStatus(422);
        challengedLogin('203.0.113.7', 'rival.student')->assertStatus(422);
    }

    challengedLogin('203.0.113.7', 'rival.student', 'password')->assertOk();
    $this->assertAuthenticatedAs($rival);
});

it('tells the owner once a day, and answers the guesser as for any identifier', function () {
    withTurnstileKeys();
    $owner = User::factory()->create(['email' => 'owner@example.test']);

    foreach (range(1, 12) as $i) {
        $known = challengedLogin('198.51.100.'.$i, 'owner@example.test');
        $unknown = challengedLogin('198.51.100.'.$i, 'nobody@example.test');

        expect($known->status())->toBe(422)
            ->and($known->json())->toBe($unknown->json());
    }

    Notification::assertSentToTimes($owner, LoginChallengeOnAccount::class, 1);
});

it('caps the notice text messages per day for the whole portal, then sends mail alone', function () {
    config(['security.login_challenge_sms_per_day' => 1]);
    MessagingChannel::query()->updateOrCreate(['channel' => MessagingChannel::SMS], [
        'provider' => 'taqnyat',
        'options' => ['sender' => 'EJAZ'],
        'secrets' => ['api_key' => 'panel-key'],
    ]);
    $first = User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => now()]);
    $second = User::factory()->create(['phone' => '+966512345679', 'phone_verified_at' => now()]);

    expect((new LoginChallengeOnAccount)->via($first))->toBe(['mail', SmsChannel::class])
        ->and((new LoginChallengeOnAccount)->via($second))->toBe(['mail']);
});

it('still caps guessing at the hard limits even when the challenge is solved', function () {
    withTurnstileKeys();
    $user = User::factory()->create(['email' => 'target@example.test']);

    // حد الحساب الصارم (15) من عناوين متفرقة
    foreach (range(1, 15) as $i) {
        challengedLogin('192.0.2.'.$i, 'target@example.test')->assertStatus(422);
    }

    Hash::spy();

    challengedLogin('192.0.2.99', 'target@example.test', 'password')->assertStatus(429);
    Hash::shouldNotHaveReceived('check');
    $this->assertGuest();

    // وسقف الشبكة الصارم (40) لحسابات مختلفة من عنوان واحد
    foreach (range(1, 40) as $i) {
        challengedLogin('203.0.113.50', "spray{$i}@example.test")->assertStatus(422);
    }

    challengedLogin('203.0.113.50', 'another@example.test')->assertStatus(429);
});

it('keeps the old blocking exactly as it was while the Turnstile keys are missing', function () {
    // المشغّل الوهمي بلا مفتاح موقع، كالتطوير والاختبارات وقبل وصول المفاتيح
    $user = User::factory()->create(['email' => 'member@example.test']);

    foreach (range(1, 3) as $i) {
        challengedLogin('203.0.113.7', "random{$i}@example.test")->assertStatus(422);
    }

    challengedLogin('203.0.113.7', 'member@example.test', 'password')->assertStatus(429);

    foreach (range(1, 10) as $i) {
        challengedLogin('198.51.100.'.$i, 'member@example.test')->assertStatus(422);
    }

    challengedLogin('198.51.100.99', 'member@example.test', 'password')->assertStatus(429);
    $this->assertGuest();
    Notification::assertNothingSent();
});

it('never lifts a limit for a failed challenge', function () {
    withTurnstileKeys(passes: false);
    User::factory()->create(['email' => 'member@example.test']);

    challengedLogin('203.0.113.7', 'member@example.test', 'password')->assertRedirect()->assertSessionHasErrors('bot');
    $this->assertGuest();
});
