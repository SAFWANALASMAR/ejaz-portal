<?php

namespace App\Actions\Messaging;

use App\Enums\MessageStatus;
use App\Enums\RoleName;
use App\Models\MessageLog;
use App\Models\MessagingChannel;
use App\Models\User;
use App\Notifications\MessagingNotConfigured;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\MessagingSettings;
use App\Support\LoginIdentifier;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * إرسال رسالة واحدة عبر قناتها وتسجيل حالتها (MessageLog) بلا نصها: أُرسلت بمعرّفها عند المزود، أو فشلت
 * برمز الخطأ، أو تجاوزت حد الرقم في اليوم، أو لا قالب لاستعمالها. والفشل يُعاد رميه لتعيد المهمة المحاولة
 * (ما لم يكن retryable=false). وغياب المزود في الإنتاج يُنبّه المشرف العام والمسؤول التقني بالبريد مرة كل ست ساعات.
 *
 * حد الرقم في اليوم يُفحص قبل كل محاولة، ويُستهلك بعد كل محاولة وصلت إلى المزود، ومنها إعادات الطابور
 * (فكل منها قد تكلّف رسالة). وما لم يُرسل أصلاً (لا مزود، أو لا قالب) لا يستهلكه.
 */
class DeliverMessage
{
    private const ALERT_SECONDS = 6 * 3600;

    /**
     * @param  Closure(): ?string  $send  الإرسال الفعلي عبر SmsGateway أو WhatsAppNotifier
     *
     * @throws MessagingException
     */
    public function handle(string $channel, string $phone, string $usage, Closure $send, int $attempt = 1): MessageLog
    {
        $provider = $channel === MessagingChannel::SMS ? MessagingSettings::sms()['provider'] : MessagingSettings::whatsapp()['provider'];

        $record = fn (MessageStatus $status, ?string $id = null, ?string $error = null): MessageLog => MessageLog::query()->create([
            'channel' => $channel,
            'provider' => $provider,
            'usage' => $usage,
            'phone_hint' => MessageLog::hint($phone),
            'status' => $status,
            'provider_message_id' => $id,
            'error' => $error,
            'attempt' => min($attempt, 255),
        ]);

        $limit = (int) config('messaging.per_number_per_day');
        $key = 'msg-number:'.$channel.':'.LoginIdentifier::hash($phone);
        $count = fn () => $limit > 0 ? RateLimiter::hit($key, 86400) : 0;

        if ($limit > 0 && RateLimiter::tooManyAttempts($key, $limit)) {
            Log::warning('Messaging per-number daily limit reached.', ['channel' => $channel, 'usage' => $usage]);

            return $record(MessageStatus::Throttled, error: 'per_number_limit');
        }

        try {
            $id = $send();
        } catch (MessagingException $exception) {
            if ($exception->reason === 'no_template') {
                return $record(MessageStatus::Skipped, error: 'no_template');
            }

            if ($exception->reason === 'not_configured') {
                $exception->log = $record(MessageStatus::Failed, error: $exception->reason);
                $this->alertNotConfigured($channel);

                throw $exception;
            }

            $count();
            $exception->log = $record(MessageStatus::Failed, error: $exception->reason);

            throw $exception;
        } catch (Throwable $exception) {
            // استثناء غير متوقع: يُسجَّل صنفه وحده، فرسالته قد تحمل تفاصيل الطلب
            $count();
            $record(MessageStatus::Failed, error: substr(class_basename($exception), 0, 100));

            throw $exception;
        }

        $count();

        // الرسالة أُرسلت فعلاً: فشل تسجيلها لا يُرمى، وإلا أعادت المهمة إرسالها
        try {
            return $record(MessageStatus::Sent, $id !== null ? mb_substr($id, 0, 191) : null);
        } catch (Throwable $exception) {
            report($exception);

            return new MessageLog(['channel' => $channel, 'provider' => $provider, 'usage' => $usage, 'phone_hint' => MessageLog::hint($phone), 'status' => MessageStatus::Sent]);
        }
    }

    /**
     * رسالة لم تُرسل لسبب قبل المزود (رمز تحقق انتهى عمره قبل أن تصل مهمته مثلاً): تُسجَّل ولا تستهلك الحد.
     */
    public function skip(string $channel, string $phone, string $usage, string $reason, int $attempt = 1): MessageLog
    {
        return MessageLog::query()->create([
            'channel' => $channel,
            'provider' => $channel === MessagingChannel::SMS ? MessagingSettings::sms()['provider'] : MessagingSettings::whatsapp()['provider'],
            'usage' => $usage,
            'phone_hint' => MessageLog::hint($phone),
            'status' => MessageStatus::Skipped,
            'error' => $reason,
            'attempt' => min($attempt, 255),
        ]);
    }

    private function alertNotConfigured(string $channel): void
    {
        if (! app()->isProduction() || ! Cache::add('messaging-not-configured:'.$channel, true, self::ALERT_SECONDS)) {
            return;
        }

        Log::critical('Messaging provider is not configured in production.', ['channel' => $channel]);

        Notification::send(
            User::role(RoleName::values(RoleName::overseers()))->where('is_active', true)->get(),
            new MessagingNotConfigured($channel),
        );
    }
}
