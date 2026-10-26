<?php

use App\Contracts\BotChallenge;
use App\Models\User;
use App\Services\BotChallenge\FakeBotChallenge;
use Illuminate\Support\Facades\Notification;

/*
| مع Turnstile مضبوطاً يصير بلوغ حد المحاولات الخاطئة لكل حساب أو عنوان تحدياً لا حجباً، حتى سقف أعلى (قرار صفوان،
| 26 أكتوبر 2026، المراجعة السحابية م١ وم٢). والتحدي يُحل في كل نموذج دخول واستعادة (VerifyBotChallenge)، فيُبدَّل
| المشغّل بالوهمي الذي يقبل. وبلا المفتاحين يبقى الحجب (LoginTest وPasswordResetTest).
*/

beforeEach(function () {
    config([
        'services.bot_challenge.driver' => 'turnstile',
        'services.bot_challenge.turnstile_site_key' => 'site-key',
        'services.bot_challenge.turnstile_secret_key' => 'secret-key',
        'security.bot_challenge_ceiling_factor' => 5,
    ]);
    app()->instance(BotChallenge::class, new FakeBotChallenge);

    $this->user = User::factory()->create(['email' => 'student@example.test']);
});

/**
 * محاولات خاطئة للحساب نفسه، كل واحدة من عنوان مختلف، فلا يبلغها إلا حد الحساب.
 */
function failLogins(int $count): void
{
    foreach (range(1, $count) as $i) {
        test()->withServerVariables(['REMOTE_ADDR' => '198.51.'.intdiv($i, 250).'.'.($i % 250 + 1)])
            ->post('/login', ['login' => 'student@example.test', 'password' => 'wrong-password']);
    }
}

it('lets the owner in past the account limit after the challenge, so a classmate cannot lock them out', function () {
    failLogins(10);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post('/login', ['login' => 'student@example.test', 'password' => 'password'])
        ->assertRedirect('/account');
    $this->assertAuthenticatedAs($this->user);
});

it('blocks again at the ceiling, even with the challenge solved', function () {
    failLogins(50);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post('/login', ['login' => 'student@example.test', 'password' => 'password'])
        ->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('keeps a school behind one address able to sign in after one student exhausts the network limit', function () {
    config(['security.login_failures_per_subscriber' => 3]);

    foreach (range(1, 3) as $i) {
        $this->post('/login', ['login' => "nobody{$i}@example.test", 'password' => 'wrong-password']);
    }

    $this->post('/login', ['login' => 'student@example.test', 'password' => 'password'])->assertRedirect('/account');
});

it('keeps the current block when the Turnstile keys are missing', function () {
    config(['services.bot_challenge.turnstile_secret_key' => '']);
    failLogins(10);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post('/login', ['login' => 'student@example.test', 'password' => 'password'])
        ->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('turns the per-address reset limit into a challenge up to its ceiling', function () {
    Notification::fake();
    config(['security.password_reset_per_subscriber_per_hour' => 2]);

    foreach (range(1, 10) as $i) {
        $this->from('/forgot-password')->post('/forgot-password', ['email' => "user{$i}@example.test"])->assertRedirect('/forgot-password');
    }

    $this->post('/forgot-password', ['email' => 'user11@example.test'])->assertTooManyRequests();
});
