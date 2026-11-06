<?php

use App\Actions\Accounts\ResetPasswordByPhone;
use App\Actions\Accounts\SendPhoneVerificationCode;
use App\Enums\MessageStatus;
use App\Jobs\SendSms;
use App\Jobs\SendWhatsAppCode;
use App\Models\MessageLog;
use App\Models\MessagingChannel;
use App\Models\User;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
| رموز التحقق عبر واتساب (قرار صفوان، 6 نوفمبر 2026 — docs/decisions/2026-11-06-whatsapp-otp.md): قالب «المصادقة» بزر
| «نسخ الرمز» متى فُعّل الاستعمال وللقناة مزود بمفاتيحه وقالب الرمز مضبوط، وإلا الرسائل النصية كما كانت.
*/

function panelWhatsAppOtp(bool $otp = true, ?string $template = 'ejaz_otp'): MessagingChannel
{
    return MessagingChannel::query()->updateOrCreate(['channel' => MessagingChannel::WHATSAPP], [
        'provider' => 'cloud',
        'options' => ['phone_number_id' => '1234567890', 'templates' => array_filter(['otp' => $template]), 'usages' => ['otp' => $otp]],
        'secrets' => ['access_token' => 'panel-token'],
    ]);
}

it('sends the code over WhatsApp when the usage, the channel and the template are all set', function () {
    Queue::fake();
    panelWhatsAppOtp();
    $user = User::factory()->create(['phone' => '+966512345678']);

    app(SendPhoneVerificationCode::class)->handle($user);

    expect(MessagingSettings::otpViaWhatsApp())->toBeTrue();
    Queue::assertPushed(SendWhatsAppCode::class, fn (SendWhatsAppCode $job) => $job->phone === '+966512345678' && preg_match('/^\d{6}$/', $job->code) === 1);
    Queue::assertNotPushed(SendSms::class);
});

it('keeps SMS when any part of the WhatsApp setup is missing', function (Closure $setup) {
    Queue::fake();
    $setup();
    $user = User::factory()->create(['phone' => '+966512345678']);

    app(SendPhoneVerificationCode::class)->handle($user);

    expect(MessagingSettings::otpViaWhatsApp())->toBeFalse();
    Queue::assertPushed(SendSms::class);
    Queue::assertNotPushed(SendWhatsAppCode::class);
})->with([
    'usage off' => [fn () => panelWhatsAppOtp(otp: false)],
    'no template' => [fn () => panelWhatsAppOtp(template: null)],
    'no channel' => [fn () => null],
]);

it('sends the authentication template with the code in the body and the copy-code button', function () {
    panelWhatsAppOtp();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OTP']]])]);

    SendWhatsAppCode::dispatchSync('+966500000123', '654321', 'رمز التحقق: 654321');

    Http::assertSent(fn (Request $request) => $request['template']['name'] === 'ejaz_otp'
        && $request['template']['components'] === [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => '654321']]],
            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => '654321']]],
        ]);

    $log = MessageLog::sole();
    expect($log->channel)->toBe(MessagingChannel::WHATSAPP)
        ->and($log->usage)->toBe('otp')
        ->and($log->status)->toBe(MessageStatus::Sent)
        ->and(json_encode($log->getAttributes()))->not->toContain('654321');
});

it('does not send a code past its lifetime', function () {
    panelWhatsAppOtp();
    Http::fake();
    $job = new SendWhatsAppCode('+966500000123', '654321', 'رمز');

    $this->travel(SendPhoneVerificationCode::TTL_MINUTES + 1)->minutes();
    app()->call([$job, 'handle']);

    Http::assertNothingSent();
    expect(MessageLog::sole())->status->toBe(MessageStatus::Skipped)->error->toBe('expired');
});

it('offers the phone password reset when codes go over WhatsApp', function () {
    app()->detectEnvironment(fn () => 'production');
    expect(ResetPasswordByPhone::available())->toBeFalse();

    panelWhatsAppOtp();

    expect(ResetPasswordByPhone::available())->toBeTrue();
});
