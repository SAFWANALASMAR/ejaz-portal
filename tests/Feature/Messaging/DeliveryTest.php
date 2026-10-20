<?php

use App\Actions\Accounts\SendPhoneVerificationCode;
use App\Actions\Messaging\DeliverMessage;
use App\Contracts\SmsGateway;
use App\Enums\MessageStatus;
use App\Enums\RoleName;
use App\Jobs\SendExamReceipt;
use App\Jobs\SendSms;
use App\Models\CompetitionSubmission;
use App\Models\MessageLog;
use App\Models\MessagingChannel;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\MessagingNotConfigured;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\MessagingSettings;
use App\Services\Sms\ConfiguredSmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\TaqnyatSmsGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function panelSms(array $options = ['sender' => 'EJAZ'], ?array $secrets = ['api_key' => 'panel-key'], string $provider = 'taqnyat'): MessagingChannel
{
    return MessagingChannel::query()->updateOrCreate(['channel' => MessagingChannel::SMS], [
        'provider' => $provider,
        'options' => $options,
        'secrets' => $secrets,
    ]);
}

it('uses the panel provider over the server environment', function () {
    config(['services.sms.driver' => 'log']);
    expect(app(ConfiguredSmsGateway::class)->resolve())->toBeInstanceOf(LogSmsGateway::class);

    panelSms();

    expect(app(ConfiguredSmsGateway::class)->resolve())->toBeInstanceOf(TaqnyatSmsGateway::class)
        ->and(MessagingSettings::sms()['source'])->toBe('panel');
});

it('uses a provider from the server environment when the panel has none', function () {
    config(['services.sms.driver' => 'msegat', 'services.sms.api_key' => 'env-key', 'services.sms.username' => 'ejaz', 'services.sms.sender' => 'EJAZ']);

    expect(MessagingSettings::ready('sms'))->toBeTrue()
        ->and(MessagingSettings::sms()['source'])->toBe('env');
});

it('keeps refusing the log driver in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new LogSmsGateway)->send('+966500000001', 'x'))->toThrow(RuntimeException::class)
        ->and(fn () => app(ConfiguredSmsGateway::class)->resolve())->toThrow(MessagingException::class, 'not_configured');
});

it('treats a provider without its key as not configured', function () {
    panelSms(secrets: null);

    expect(MessagingSettings::ready('sms'))->toBeFalse()
        ->and(fn () => app(ConfiguredSmsGateway::class)->resolve())->toThrow(MessagingException::class, 'not_configured');
});

it('sends a queued code and records its status without the code', function () {
    panelSms();
    Http::fake(['api.taqnyat.sa/*' => Http::response(['messageId' => 777], 201)]);

    SendSms::dispatchSync('+966500000123', 'رمز التحقق: 654321');

    $log = MessageLog::sole();
    expect($log->status)->toBe(MessageStatus::Sent)
        ->and($log->provider)->toBe('taqnyat')
        ->and($log->usage)->toBe('otp')
        ->and($log->provider_message_id)->toBe('777')
        ->and($log->phone_hint)->toBe('***0123')
        ->and(json_encode($log->getAttributes()))->not->toContain('654321')->not->toContain('500000123');
});

it('records a failure and lets the queue retry on a temporary error', function () {
    panelSms();
    Http::fake(['api.taqnyat.sa/*' => Http::response('', 503)]);

    expect(fn () => SendSms::dispatchSync('+966500000123', 'رمز'))->toThrow(MessagingException::class);

    expect(MessageLog::sole())->status->toBe(MessageStatus::Failed)->error->toBe('http_503');
});

it('does not retry a rejected key, and fails the job at once', function () {
    panelSms();
    Http::fake(['api.taqnyat.sa/*' => Http::response(['message' => 'invalid credentials information'], 401)]);

    $job = (new SendSms('+966500000123', 'رمز'))->withFakeQueueInteractions();
    $job->handle(app(SmsGateway::class), app(DeliverMessage::class));

    $job->assertFailed();
    expect(MessageLog::sole()->error)->toBe('http_401');
});

it('alerts the super admins once when production has no SMS provider, and keeps verification working', function () {
    Notification::fake();
    Queue::fake();
    app()->detectEnvironment(fn () => 'production');
    $admin = userWithRole(RoleName::SuperAdmin);
    $user = User::factory()->create(['phone' => '+966500000123', 'phone_verified_at' => null]);

    // التوثيق كما هو: يُولَّد الرمز ويُرسل إلى الطابور
    app(SendPhoneVerificationCode::class)->handle($user);
    Queue::assertPushed(SendSms::class);

    foreach ([1, 2] as $attempt) {
        $job = (new SendSms('+966500000123', 'رمز'))->withFakeQueueInteractions();
        $job->handle(app(SmsGateway::class), app(DeliverMessage::class));
        $job->assertFailed();
    }

    Notification::assertSentToTimes($admin, MessagingNotConfigured::class, 1);
    expect(MessageLog::where('error', 'not_configured')->count())->toBe(2);
});

it('stops at the daily limit per number across all uses', function () {
    config(['messaging.per_number_per_day' => 2]);
    panelSms();
    Http::fake(['api.taqnyat.sa/*' => Http::response(['messageId' => 1], 201)]);

    foreach (range(1, 3) as $i) {
        SendSms::dispatchSync('+966500000123', 'رمز '.$i);
    }
    SendSms::dispatchSync('+966500000999', 'رمز');

    Http::assertSentCount(3);
    expect(MessageLog::where('status', MessageStatus::Throttled)->count())->toBe(1)
        ->and(MessageLog::where('status', MessageStatus::Sent)->count())->toBe(3);
});

it('keeps the existing per-number and per-network code limits', function () {
    Queue::fake();
    $user = User::factory()->create(['phone' => '+966500000123', 'phone_verified_at' => null]);

    app(SendPhoneVerificationCode::class)->handle($user);

    // رمز واحد في الدقيقة للمستخدم، والحدود الأخرى في PhoneVerificationTest
    expect(fn () => app(SendPhoneVerificationCode::class)->handle($user))->toThrow(ValidationException::class);
});

it('sends the exam receipt with its approved template when enabled from the panel', function () {
    config(['services.whatsapp.driver' => 'log', 'competitions.exam.whatsapp' => false]);
    MessagingChannel::query()->create([
        'channel' => MessagingChannel::WHATSAPP,
        'provider' => 'cloud',
        'options' => ['phone_number_id' => '1234567890', 'templates' => ['exam_receipt' => 'ejaz_exam_receipt'], 'usages' => ['exam_receipt' => true]],
        'secrets' => ['access_token' => 'wa-token'],
    ]);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]])]);

    expect(MessagingSettings::usageEnabled('whatsapp', 'exam_receipt'))->toBeTrue();

    $submission = CompetitionSubmission::factory()->submitted()->create(['user_id' => User::factory()->create(['phone' => '+966500000124'])->id]);

    SendExamReceipt::dispatchSync($submission->id);

    Http::assertSent(fn (Request $request) => $request['template']['name'] === 'ejaz_exam_receipt' && count($request['template']['components'][0]['parameters']) === 4);
    expect(MessageLog::sole())->status->toBe(MessageStatus::Sent)->provider_message_id->toBe('wamid.X');
});

it('skips the exam receipt without an approved template', function () {
    config(['services.whatsapp.driver' => 'log']);
    MessagingChannel::query()->create([
        'channel' => MessagingChannel::WHATSAPP,
        'provider' => 'cloud',
        'options' => ['phone_number_id' => '1234567890'],
        'secrets' => ['access_token' => 'wa-token'],
    ]);
    Http::fake();

    $submission = CompetitionSubmission::factory()->submitted()->create(['user_id' => User::factory()->create(['phone' => '+966500000124'])->id]);

    SendExamReceipt::dispatchSync($submission->id);

    Http::assertNothingSent();
    expect(MessageLog::sole())->status->toBe(MessageStatus::Skipped)->error->toBe('no_template');
});

it('lets the panel turn a use off even when its server key is on', function () {
    config(['services.sms.request_updates' => true]);
    expect(MessagingSettings::usageEnabled('sms', 'request_updates'))->toBeTrue();

    panelSms(['sender' => 'EJAZ', 'usages' => ['request_updates' => false]]);

    expect(MessagingSettings::usageEnabled('sms', 'request_updates'))->toBeFalse();
});

it('prunes status records after the retention period', function () {
    config(['messaging.log_days' => 90]);
    $old = MessageLog::query()->create(['channel' => 'sms', 'provider' => 'log', 'usage' => 'otp', 'phone_hint' => '***0001', 'status' => MessageStatus::Sent]);
    $old->forceFill(['created_at' => now()->subDays(91)])->save();
    MessageLog::query()->create(['channel' => 'sms', 'provider' => 'log', 'usage' => 'otp', 'phone_hint' => '***0002', 'status' => MessageStatus::Sent]);

    $this->artisan('model:prune', ['--model' => [MessageLog::class]])->assertSuccessful();

    expect(MessageLog::pluck('phone_hint')->all())->toBe(['***0002']);
});

it('cuts a long provider error code to fit its column', function () {
    panelSms();
    Http::fake(['api.taqnyat.sa/*' => Http::response('', 400)]);
    $long = new MessagingException(str_repeat('x', 300));

    expect(mb_strlen($long->reason))->toBe(100);

    try {
        app(DeliverMessage::class)->handle('sms', '+966500000123', 'otp', fn () => throw $long);
    } catch (MessagingException $exception) {
        expect($exception->log?->error)->toHaveLength(100);
    }
});

it('does not resend a verification code after a read timeout, but retries a notification', function () {
    panelSms();
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 10001 milliseconds with 0 bytes received'));

    $otp = (new SendSms('+966500000123', 'رمز: 111111'))->withFakeQueueInteractions();
    $otp->handle(app(SmsGateway::class), app(DeliverMessage::class));
    $otp->assertFailed();

    $notice = (new SendSms('+966500000124', 'إشعار', 'notification'))->withFakeQueueInteractions();
    expect(fn () => $notice->handle(app(SmsGateway::class), app(DeliverMessage::class)))->toThrow(MessagingException::class);
    $notice->assertNotFailed();

    expect(MessageLog::pluck('error')->unique()->all())->toBe(['timeout_unknown']);
});

it('retries a verification code that never reached the provider', function () {
    panelSms();
    Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to api.taqnyat.sa port 443'));

    $job = (new SendSms('+966500000123', 'رمز'))->withFakeQueueInteractions();

    expect(fn () => $job->handle(app(SmsGateway::class), app(DeliverMessage::class)))->toThrow(MessagingException::class);
    $job->assertNotFailed();
});

it('drops a verification code job older than the code itself', function () {
    panelSms();
    Http::fake();
    $job = new SendSms('+966500000123', 'رمز');

    $this->travel(SendPhoneVerificationCode::TTL_MINUTES + 1)->minutes();
    $job->handle(app(SmsGateway::class), app(DeliverMessage::class));

    Http::assertNothingSent();
    expect(MessageLog::sole())->status->toBe(MessageStatus::Skipped)->error->toBe('expired');
});

it('counts every attempt that reached the provider, retries included', function () {
    config(['messaging.per_number_per_day' => 2]);
    panelSms();
    Http::fake(['api.taqnyat.sa/*' => Http::response('', 503)]);
    $deliver = app(DeliverMessage::class);
    $send = fn () => app(SmsGateway::class)->send('+966500000123', 'رمز');

    foreach ([1, 2] as $attempt) {
        expect(fn () => $deliver->handle('sms', '+966500000123', 'otp', $send, $attempt))->toThrow(MessagingException::class);
    }

    expect($deliver->handle('sms', '+966500000123', 'otp', $send, 3)->status)->toBe(MessageStatus::Throttled);
    Http::assertSentCount(2);
});

it('does not use the daily quota when nothing was sent', function () {
    config(['messaging.per_number_per_day' => 1]);
    $deliver = app(DeliverMessage::class);

    expect(fn () => $deliver->handle('sms', '+966500000123', 'otp', fn () => throw MessagingException::notConfigured()))->toThrow(MessagingException::class);
    $deliver->handle('whatsapp', '+966500000123', 'exam_receipt', fn () => throw new MessagingException('no_template'));

    expect($deliver->handle('sms', '+966500000123', 'otp', fn () => 'id-1')->status)->toBe(MessageStatus::Sent)
        ->and($deliver->handle('whatsapp', '+966500000123', 'exam_receipt', fn () => 'wamid.1')->status)->toBe(MessageStatus::Sent);
});

it('keeps a final SMS failure in the log without failing the whole notification, and retries a temporary one', function () {
    panelSms();
    $user = User::factory()->create(['phone' => '+966500000123', 'phone_verified_at' => now()]);
    $notification = new class extends Illuminate\Notifications\Notification
    {
        public function toSms(object $notifiable): string
        {
            return 'إشعار';
        }
    };
    $channel = app(SmsChannel::class);

    Http::fakeSequence('api.taqnyat.sa/*')->push(['message' => 'invalid credentials information'], 401)->push('', 503);

    $channel->send($user, $notification);
    expect(MessageLog::sole()->error)->toBe('http_401')
        ->and(fn () => $channel->send($user, $notification))->toThrow(MessagingException::class);
});

it('sends verification codes to Saudi mobile numbers only', function () {
    Queue::fake();
    $user = User::factory()->create(['phone' => '+447700900123', 'phone_verified_at' => null]);

    expect(fn () => app(SendPhoneVerificationCode::class)->handle($user))
        ->toThrow(ValidationException::class, __('account.phone.not_saudi'));
    Queue::assertNothingPushed();
});

it('refuses an international number at registration', function () {
    $this->post('/register', [
        'name' => 'متقدم',
        'email' => 'intl@example.test',
        'phone' => '+447700900123',
        'password' => 'Long-Enough-Pass-2026',
        'password_confirmation' => 'Long-Enough-Pass-2026',
        'privacy' => '1',
    ])->assertSessionHasErrors('phone');

    expect(User::where('email', 'intl@example.test')->exists())->toBeFalse();
});
