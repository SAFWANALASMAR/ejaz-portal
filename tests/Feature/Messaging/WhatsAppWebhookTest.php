<?php

use App\Actions\Messaging\DeliverMessage;
use App\Actions\Messaging\HandleWhatsAppWebhook;
use App\Enums\ActivityEvent;
use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Models\MessagingChannel;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;

const WEBHOOK_APP_SECRET = 'meta-app-secret';

beforeEach(function () {
    MessagingChannel::query()->create([
        'channel' => MessagingChannel::WHATSAPP,
        'provider' => 'cloud',
        'options' => ['phone_number_id' => '1234567890'],
        'secrets' => ['access_token' => 'wa-token', 'app_secret' => WEBHOOK_APP_SECRET, 'verify_token' => 'verify-me'],
    ]);
});

/**
 * @param  list<array<string, mixed>>  $statuses
 */
function statusPayload(array $statuses): string
{
    return (string) json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '1', 'changes' => [['field' => 'messages', 'value' => ['statuses' => $statuses]]]]]]);
}

function postStatus(string $body, ?string $secret = WEBHOOK_APP_SECRET)
{
    $headers = ['CONTENT_TYPE' => 'application/json'];

    if ($secret !== null) {
        $headers['HTTP_X_HUB_SIGNATURE_256'] = 'sha256='.hash_hmac('sha256', $body, $secret);
    }

    return test()->call('POST', '/webhooks/whatsapp', [], [], [], $headers, $body);
}

function sentWhatsApp(string $id = 'wamid.1'): MessageLog
{
    return MessageLog::query()->create(['channel' => 'whatsapp', 'provider' => 'cloud', 'usage' => 'exam_receipt', 'phone_hint' => '***0001', 'status' => MessageStatus::Sent, 'provider_message_id' => $id]);
}

it('answers the subscription check with the right token only', function () {
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=1158201444')->assertOk()->assertSeeText('1158201444');
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1158201444')->assertForbidden();
});

// مراجعة ما بعد v0.12.0 (محتملة ٥): الإشعار قد يصل والإرسال لم يرجع بعد، فلا يضيع
it('keeps a status that arrives before its message is recorded, and applies it once the message is recorded', function () {
    postStatus(statusPayload([['id' => 'wamid.early', 'status' => 'delivered'], ['id' => 'wamid.early', 'status' => 'read']]))->assertOk();

    $log = app(DeliverMessage::class)->handle(MessagingChannel::WHATSAPP, '0500000001', 'exam_receipt', fn () => 'wamid.early');

    expect($log->fresh()->status)->toBe(MessageStatus::Read);

    // ويُطبَّق مرة: رسالة أخرى بالمعرّف نفسه لا ترثه، والفشل المبكر برمزه
    postStatus(statusPayload([['id' => 'wamid.failed', 'status' => 'failed', 'errors' => [['code' => 131026]]]]))->assertOk();
    $failed = app(DeliverMessage::class)->handle(MessagingChannel::WHATSAPP, '0500000002', 'exam_receipt', fn () => 'wamid.failed');
    $again = app(DeliverMessage::class)->handle(MessagingChannel::WHATSAPP, '0500000003', 'exam_receipt', fn () => 'wamid.early');

    expect($failed->fresh())->status->toBe(MessageStatus::Failed)->error->toBe('meta:131026')
        ->and($again->fresh()->status)->toBe(MessageStatus::Sent);
});

it('updates delivery status forward only', function () {
    $log = sentWhatsApp();

    postStatus(statusPayload([['id' => 'wamid.1', 'status' => 'read', 'timestamp' => '1', 'recipient_id' => '966500000001']]))->assertOk();
    expect($log->fresh()->status)->toBe(MessageStatus::Read);

    // «سُلّمت» المتأخرة لا تعيدها
    postStatus(statusPayload([['id' => 'wamid.1', 'status' => 'delivered']]))->assertOk();
    expect($log->fresh()->status)->toBe(MessageStatus::Read);
});

it('records a failed delivery with its code', function () {
    $log = sentWhatsApp();

    postStatus(statusPayload([['id' => 'wamid.1', 'status' => 'failed', 'errors' => [['code' => 131026, 'title' => 'Message undeliverable']]]]))->assertOk();

    expect($log->fresh())->status->toBe(MessageStatus::Failed)->error->toBe('meta:131026');
});

it('rejects a missing or wrong signature and records it', function (?string $secret) {
    $log = sentWhatsApp();

    postStatus(statusPayload([['id' => 'wamid.1', 'status' => 'read']]), $secret)->assertForbidden();

    expect($log->fresh()->status)->toBe(MessageStatus::Sent)
        ->and(Activity::where('event', ActivityEvent::MessagingWebhookRejected->value)->count())->toBe(1);
})->with(['missing' => [null], 'wrong secret' => ['not-the-secret']]);

it('rejects every notification while no app secret is configured', function () {
    MessagingChannel::query()->where('channel', 'whatsapp')->update(['secrets' => null]);
    MessagingChannel::query()->where('channel', 'whatsapp')->first()->forceFill(['secrets' => ['access_token' => 'wa-token']])->save();

    postStatus(statusPayload([]), 'anything')->assertForbidden();
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=&hub.challenge=1')->assertForbidden();
});

it('needs no session or CSRF token', function () {
    postStatus(statusPayload([]))->assertOk()->assertCookieMissing(config('session.cookie'));
});

it('records repeated rejected notifications from one address only once', function () {
    foreach (range(1, 5) as $i) {
        postStatus(statusPayload([]), 'not-the-secret')->assertForbidden();
    }

    expect(Activity::where('event', ActivityEvent::MessagingWebhookRejected->value)->count())->toBe(1);
});

it('records nothing while no app secret is configured', function () {
    MessagingChannel::query()->where('channel', 'whatsapp')->first()->forceFill(['secrets' => ['access_token' => 'wa-token']])->save();

    postStatus(statusPayload([]), 'anything')->assertForbidden();

    expect(Activity::where('event', ActivityEvent::MessagingWebhookRejected->value)->count())->toBe(0);
});

it('keeps a failure final, even when an earlier status arrives late', function () {
    $log = sentWhatsApp();

    postStatus(statusPayload([['id' => 'wamid.1', 'status' => 'failed', 'errors' => [['code' => 131026]]]]))->assertOk();
    postStatus(statusPayload([['id' => 'wamid.1', 'status' => 'delivered']]))->assertOk();

    expect($log->fresh())->status->toBe(MessageStatus::Failed)->error->toBe('meta:131026');
});

it('clears an old error when the status moves forward', function () {
    $log = sentWhatsApp();
    $log->update(['error' => 'stale']);

    postStatus(statusPayload([['id' => 'wamid.1', 'status' => 'delivered']]))->assertOk();

    expect($log->fresh())->status->toBe(MessageStatus::Delivered)->error->toBeNull();
});

it('limits and records rejected notifications per IPv6 /64, with a cap for everyone', function () {
    $from = fn (string $ip) => test()->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $ip, 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], '{}');

    // عنوانان في /64 واحدة: صف واحد
    $from('2001:db8:1:1::1')->assertForbidden();
    $from('2001:db8:1:1::2')->assertForbidden();
    expect(Activity::where('event', ActivityEvent::MessagingWebhookRejected->value)->count())->toBe(1);

    // بعد السقف العام لا صفوف جديدة ولو من مشترك جديد
    foreach (range(1, HandleWhatsAppWebhook::REJECTED_PER_HOUR) as $i) {
        RateLimiter::hit('whatsapp-webhook-rejected:all', 3600);
    }
    $from('2001:db8:2:2::1')->assertForbidden();
    expect(Activity::where('event', ActivityEvent::MessagingWebhookRejected->value)->count())->toBe(1);

    // والحد لكل /64 لا يُلتف عليه بتدوير العناوين
    config(['messaging.webhook_per_minute' => 2]);
    $from('2001:db8:3:3::1')->assertForbidden();
    $from('2001:db8:3:3::2')->assertForbidden();
    $from('2001:db8:3:3::3')->assertTooManyRequests();
    $from('2001:db8:4:4::1')->assertForbidden();
});
