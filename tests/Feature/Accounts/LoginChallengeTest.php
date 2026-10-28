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

/*
| مع Turnstile مضبوطاً يصير بلوغ حد المحاولات الخاطئة للشبكة تحدياً لا حجباً، حتى سقف أعلى (قرار صفوان، 26 أكتوبر 2026،
| المراجعة السحابية م٢). وحدود الحساب حاجبة بقيمها دائماً (المراجعة المستقلة على v0.14.0، قرار صفوان ١(أ)). والتحدي يُحل
| في كل نموذج دخول واستعادة (VerifyBotChallenge)، فيُبدَّل المشغّل بالوهمي الذي يقبل. وبلا المفتاحين يبقى الحجب (LoginTest
| وPasswordResetTest). وصاحب الحساب يُنبَّه مرة في اليوم حين يُقفل حسابه.
*/

beforeEach(function () {
    Notification::fake();

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

function challengedLogin(string $ip, string $login, string $password = 'wrong-password'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/login', ['login' => $login, 'password' => $password, 'cf-turnstile-response' => 'token']);
}

it('locks the account at its own limit even with the challenge solved, before checking the password', function () {
    // عشر محاولات خاطئة بالتحدي محلولاً، كل واحدة من عنوان، فلا يبلغها إلا حد الحساب الأصلي (10 في ربع ساعة)
    foreach (range(1, 10) as $i) {
        challengedLogin('198.51.100.'.$i, 'student@example.test')->assertStatus(422);
    }

    Hash::spy();

    challengedLogin('203.0.113.7', 'student@example.test', 'password')->assertStatus(429);
    Hash::shouldNotHaveReceived('check');
    $this->assertGuest();
});

it('locks the account at its own limit whichever of its identifiers is tried', function () {
    $this->user->update(['username' => 'rival.student']);

    foreach (range(1, 5) as $i) {
        challengedLogin('198.51.100.'.$i, 'student@example.test')->assertStatus(422);
        challengedLogin('198.51.101.'.$i, 'rival.student')->assertStatus(422);
    }

    challengedLogin('203.0.113.7', 'rival.student', 'password')->assertStatus(429);
    $this->assertGuest();
});

it('keeps the per-minute limit for one address on the account at its own value with the challenge solved', function () {
    foreach (range(1, 5) as $i) {
        challengedLogin('203.0.113.7', 'student@example.test')->assertStatus(422);
    }

    challengedLogin('203.0.113.7', 'student@example.test', 'password')->assertStatus(429);
    $this->assertGuest();
});

it('keeps a school behind one address able to sign in after one student exhausts the network limit', function () {
    config(['security.login_failures_per_subscriber' => 3]);

    foreach (range(1, 3) as $i) {
        $this->post('/login', ['login' => "nobody{$i}@example.test", 'password' => 'wrong-password']);
    }

    $this->post('/login', ['login' => 'student@example.test', 'password' => 'password'])->assertRedirect('/account');
});

it('still blocks password spraying from one address at the network ceiling', function () {
    config(['security.login_failures_per_subscriber' => 3]);

    foreach (range(1, 15) as $i) {
        challengedLogin('203.0.113.50', "spray{$i}@example.test")->assertStatus(422);
    }

    challengedLogin('203.0.113.50', 'student@example.test', 'password')->assertStatus(429);
    $this->assertGuest();
});

it('tells the owner once a day, and answers the guesser as for any identifier', function () {
    // عشر خاطئة، ثم القفل: الرد في الحالين واحد لمعرّف موجود وغيره. والوقت مجمّد، لأن رسالة القفل تحمل الثواني الباقية،
    // فطلبان على جانبي حد ثانية يختلفان بثانية (899 و900) ولا خلل
    $this->freezeTime();
    foreach (range(1, 12) as $i) {
        $known = challengedLogin('198.51.100.'.$i, 'student@example.test');
        $unknown = challengedLogin('198.51.100.'.$i, 'nobody@example.test');

        expect($known->status())->toBe($i <= 10 ? 422 : 429)
            ->and($unknown->status())->toBe($known->status())
            ->and($known->json())->toBe($unknown->json());
    }

    Notification::assertSentToTimes($this->user, LoginChallengeOnAccount::class, 1);
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

it('keeps the current block, and sends no notice, when the Turnstile keys are missing', function () {
    config(['services.bot_challenge.turnstile_secret_key' => '']);
    failLogins(10);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post('/login', ['login' => 'student@example.test', 'password' => 'password'])
        ->assertSessionHasErrors('login');
    $this->assertGuest();

    config(['security.login_failures_per_subscriber' => 3]);

    foreach (range(1, 3) as $i) {
        challengedLogin('192.0.2.7', "nobody{$i}@example.test")->assertStatus(422);
    }

    challengedLogin('192.0.2.7', 'other@example.test', 'password')->assertStatus(429);
    Notification::assertNothingSent();
});

it('never lifts a limit for a failed challenge', function () {
    app()->instance(BotChallenge::class, new FakeBotChallenge(passes: false));

    challengedLogin('203.0.113.7', 'student@example.test', 'password')->assertRedirect()->assertSessionHasErrors('bot');
    $this->assertGuest();
});

it('keeps the per-address reset limit blocking, since it guards the cost of messages', function () {
    config(['security.password_reset_per_subscriber_per_hour' => 2]);

    foreach (range(1, 2) as $i) {
        $this->from('/forgot-password')->post('/forgot-password', ['email' => "user{$i}@example.test"])->assertRedirect('/forgot-password');
    }

    $this->post('/forgot-password', ['email' => 'user3@example.test'])->assertTooManyRequests();
});

it('caps the notice text messages for each targeted account, then sends mail alone', function () {
    config(['security.login_challenge_sms_per_account_per_week' => 2, 'security.login_challenge_sms_per_day' => 200]);
    MessagingChannel::query()->updateOrCreate(['channel' => MessagingChannel::SMS], [
        'provider' => 'taqnyat',
        'options' => ['sender' => 'EJAZ'],
        'secrets' => ['api_key' => 'panel-key'],
    ]);
    $target = User::factory()->create(['phone' => '+966512345670', 'phone_verified_at' => now()]);
    $other = User::factory()->create(['phone' => '+966512345671', 'phone_verified_at' => now()]);

    expect((new LoginChallengeOnAccount)->via($target))->toBe(['mail', SmsChannel::class])
        ->and((new LoginChallengeOnAccount)->via($target))->toBe(['mail', SmsChannel::class])
        ->and((new LoginChallengeOnAccount)->via($target))->toBe(['mail'])
        // وحساب آخر برصيده هو، والبوابة دون سقفها
        ->and((new LoginChallengeOnAccount)->via($other))->toBe(['mail', SmsChannel::class]);
});

it('lists the challenge settings in both environment templates', function (string $template) {
    $content = (string) file_get_contents(base_path($template));

    foreach (['BOT_CHALLENGE_CEILING_FACTOR', 'LOGIN_CHALLENGE_SMS_PER_DAY', 'LOGIN_CHALLENGE_SMS_PER_ACCOUNT_PER_WEEK', 'NATIONAL_ID_REVEALS_PER_HOUR'] as $variable) {
        expect($content)->toMatch('/^'.$variable.'=\d+$/m');
    }
})->with(['.env.example', '.env.production.example']);

it('keeps the account weekly allowance when the portal daily cap stops the text message', function () {
    config(['security.login_challenge_sms_per_account_per_week' => 1, 'security.login_challenge_sms_per_day' => 1]);
    MessagingChannel::query()->updateOrCreate(['channel' => MessagingChannel::SMS], [
        'provider' => 'taqnyat',
        'options' => ['sender' => 'EJAZ'],
        'secrets' => ['api_key' => 'panel-key'],
    ]);
    $first = User::factory()->create(['phone' => '+966512345672', 'phone_verified_at' => now()]);
    $target = User::factory()->create(['phone' => '+966512345673', 'phone_verified_at' => now()]);

    // سقف البوابة نفد اليوم: لا رسالة، ولا يُخصم من حصة الحساب، فتصله رسالة غداً
    expect((new LoginChallengeOnAccount)->via($first))->toBe(['mail', SmsChannel::class])
        ->and((new LoginChallengeOnAccount)->via($target))->toBe(['mail']);

    $this->travel(1)->days();

    expect((new LoginChallengeOnAccount)->via($target))->toBe(['mail', SmsChannel::class]);
});
