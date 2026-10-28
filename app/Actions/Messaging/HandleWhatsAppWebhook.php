<?php

namespace App\Actions\Messaging;

use App\Enums\ActivityEvent;
use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Models\MessagingChannel;
use App\Services\Messaging\MessagingSettings;
use App\Support\NetworkPrefix;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * إشعارات حالة التسليم من WhatsApp Cloud API. التوقيع إلزامي: رأس X-Hub-Signature-256 بقيمة
 * sha256=HMAC-SHA256(جسم الطلب الخام، App Secret)، فبلا App Secret مضبوط يُرفض كل إشعار (docs/decisions.md).
 * والتوقيع الخاطئ يُسجَّل في سجل العمليات مرة لكل مشترك كل عشر دقائق، وبسقف عام في الساعة.
 * ويحدّث حالة الرسالة بمعرّفها، تقدماً فقط (أُرسلت ← سُلّمت ← قُرئت) يمحو الخطأ، والفشل برمز خطئه نهائي.
 */
class HandleWhatsAppWebhook
{
    // سقف صفوف الرفض في سجل العمليات للبوابة كلها في الساعة
    public const REJECTED_PER_HOUR = 60;

    private const REJECTED_ALL = 'whatsapp-webhook-rejected:all';

    // مدة حفظ حالة وصلت قبل تسجيل رسالتها
    public const PENDING_SECONDS = 600;

    /**
     * @throws AuthorizationException توقيع غائب أو خاطئ، أو App Secret غير مضبوط
     */
    public function handle(Request $request): int
    {
        $secret = MessagingSettings::whatsapp()['app_secret'];
        $signature = (string) $request->header('X-Hub-Signature-256');

        // بلا App Secret يُرفض كل إشعار بلا تسجيل: لا شيء يُتحقق منه بعد
        if ($secret === null) {
            throw new AuthorizationException;
        }

        if (! str_starts_with($signature, 'sha256=')
            || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), substr($signature, 7))) {
            // مرة لكل مشترك (عنوان IPv4، أو /64 في IPv6 فلا يُلتف عليه بتدوير العناوين) كل عشر دقائق، وبسقف
            // عام في الساعة، فلا يُضخَّم سجل العمليات بطلبات مرفوضة من عناوين كثيرة
            $subscriber = NetworkPrefix::subscriber($request->ip());

            if (! RateLimiter::tooManyAttempts(self::REJECTED_ALL, self::REJECTED_PER_HOUR)
                && Cache::add('whatsapp-webhook-rejected:'.sha1($subscriber), true, 600)) {
                RateLimiter::hit(self::REJECTED_ALL, 3600);

                activity('security')
                    ->event(ActivityEvent::MessagingWebhookRejected->value)
                    ->withProperties(['ip' => $request->ip()])
                    ->log(ActivityEvent::MessagingWebhookRejected->value);
            }

            throw new AuthorizationException;
        }

        $updated = 0;

        foreach ((array) $request->json('entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                foreach ((array) data_get($change, 'value.statuses', []) as $status) {
                    $updated += $this->apply((array) $status);
                }
            }
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function apply(array $status): int
    {
        $id = $status['id'] ?? null;
        $new = MessageStatus::tryFrom(is_string($status['status'] ?? null) ? $status['status'] : '');

        if (! is_string($id) || $id === '' || ! in_array($new, [MessageStatus::Sent, MessageStatus::Delivered, MessageStatus::Read, MessageStatus::Failed], true)) {
            return 0;
        }

        $code = data_get($status, 'errors.0.code');
        $error = is_scalar($code) ? mb_substr('meta:'.$code, 0, 100) : 'failed';
        $log = self::log($id);

        if ($log === null) {
            // الإشعار قد يسبق تسجيل الرسالة (يصل والإرسال لم يرجع بعد)، فيُحفظ قليلاً ويُطبَّق عند تسجيلها
            // (DeliverMessage)، ثم يُعاد البحث مرة لرسالة سُجّلت بين البحث والحفظ (مراجعة ما بعد v0.12.0، محتملة ٥)
            $pending = (array) Cache::get(self::pendingKey($id), []);
            Cache::put(self::pendingKey($id), [...$pending, $new->value => $error], self::PENDING_SECONDS);
            $log = self::log($id);

            return $log === null ? 0 : self::applyPending($log);
        }

        return self::transition($log, $new, $error);
    }

    /**
     * ما وصل من حالات الرسالة قبل تسجيلها، يُطبَّق مرة ويُحذف.
     */
    public static function applyPending(MessageLog $log): int
    {
        if ($log->channel !== MessagingChannel::WHATSAPP || $log->provider_message_id === null) {
            return 0;
        }

        $updated = 0;

        foreach ((array) Cache::pull(self::pendingKey($log->provider_message_id), []) as $status => $error) {
            $new = MessageStatus::tryFrom((string) $status);
            $updated += $new === null ? 0 : self::transition($log, $new, (string) $error);
        }

        return $updated;
    }

    private static function log(string $id): ?MessageLog
    {
        return MessageLog::query()->where('channel', MessagingChannel::WHATSAPP)->where('provider_message_id', $id)->latest('id')->first();
    }

    private static function pendingKey(string $id): string
    {
        return 'whatsapp-status-pending:'.sha1($id);
    }

    private static function transition(MessageLog $log, MessageStatus $new, string $error): int
    {
        // الفشل نهائي: لا يغيّره إشعار يصل بعده، ولو كان «سُلّمت» متأخراً من قبله
        if ($log->status === MessageStatus::Failed) {
            return 0;
        }

        if ($new === MessageStatus::Failed) {
            return (int) $log->update(['status' => $new, 'error' => $error]);
        }

        if ($new->rank() <= $log->status->rank()) {
            return 0;
        }

        // التقدم يمحو أي خطأ سابق، فلا تُعرض «سُلّمت» برمز خطأ
        return (int) $log->update(['status' => $new, 'error' => null]);
    }
}
