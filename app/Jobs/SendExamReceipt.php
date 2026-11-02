<?php

namespace App\Jobs;

use App\Actions\Messaging\DeliverMessage;
use App\Contracts\WhatsAppNotifier;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionSubmission;
use App\Models\MessagingChannel;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\MessagingSettings;
use App\Services\WhatsApp\WhatsAppMessage;
use App\Support\Dates;
use App\Support\LoginIdentifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * إشعار واتساب بعد إرسال الاختبار، رسالة واحدة إلى جوال ولي الأمر في التسجيل إن وُجد، وإلا جوال الحساب (قرار صفوان،
 * 31 أكتوبر 2026): تأكيد الاستلام، وتاريخ الإرسال ووقته بتوقيت الرياض، مختوماً باسم الجمعية. ولا إجابات ولا درجة. والمزوّد خلف WhatsAppNotifier: قالب معتمد
 * (exam_receipt) متغيراته بالترتيب: المسابقة، والتاريخ، والوقت، واسم الجمعية (docs/guides/messaging.md).
 */
class SendExamReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * مهلة الإعادة بالثواني بعد فشل مؤقت عند المزود، فلا تُعاد المحاولات فوراً متتابعة.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120];

    /**
     * @param  int|null  $attemptId  محاولة في مسابقة الطلاب (SubmitAttempt): وقت تسليمها لا وقت التسجيل
     */
    public function __construct(public int $submissionId, public ?int $attemptId = null) {}

    public function handle(WhatsAppNotifier $whatsapp, DeliverMessage $deliver): void
    {
        $submission = CompetitionSubmission::query()->with(['user', 'competition'])->find($this->submissionId);
        $phone = filled($submission?->guardian_phone) ? $submission->guardian_phone : $submission?->user?->phone;
        $submittedAt = $this->attemptId !== null
            ? CompetitionAttempt::query()->where('submission_id', $this->submissionId)->find($this->attemptId)?->submitted_at
            : $submission?->submitted_at;

        if ($submission === null || $submittedAt === null || blank($phone)) {
            return;
        }

        $local = Dates::local($submittedAt);

        $parameters = [
            'competition' => $submission->competition->title,
            'date' => $local->format('Y-m-d'),
            'time' => $local->format('H:i'),
            'association' => __('competitions.exam.association'),
        ];

        $message = new WhatsAppMessage('exam_receipt', array_values($parameters), __('competitions.exam.whatsapp_message', $parameters));

        if (($limit = $this->overLimit((string) $phone)) !== null) {
            Log::warning('Exam receipt WhatsApp limit reached; receipt skipped.', ['limit' => $limit]);
            $deliver->skip(MessagingChannel::WHATSAPP, (string) $phone, 'exam_receipt', $limit.'_limit', $this->attempts());

            return;
        }

        try {
            $deliver->handle(MessagingChannel::WHATSAPP, (string) $phone, 'exam_receipt', fn () => $whatsapp->send((string) $phone, $message), $this->attempts());
        } catch (MessagingException $exception) {
            // ولا يُعاد بعد مهلة القراءة: ربما وصل، فلا يتكرر الإيصال (مراجعة ما بعد v0.12.0)
            if (! $exception->retryable || $exception->outcomeUnknown) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    /**
     * حدا إيصال واتساب كحد الرسائل النصية (SendPhoneVerificationCode): لكل رقم في اليوم (ولي أمر لعدة أبناء، أو رقم
     * أُدخل في تسجيلات كثيرة)، وللبوابة كلها في اليوم حارساً للتكلفة. ويعيد اسم الحد المتجاوز، أو null.
     *
     * - لا يُعدّ إلا ما يمكن إرساله: مزود واتساب حقيقي بقالب الإيصال. وما عداه يتخطاه DeliverMessage بسببه ولا يستهلك الحد.
     * - وعلامة «عُدّ» لكل محاولة في الذاكرة المؤقتة تُوضع بعد العدّ لا قبله: فإعادة المهمة بعد العدّ لا تعدّه ثانية، والسقوط
     *   بين العدّ والعلامة يعدّه مرة زائدة في الإعادة لا ناقصة، فالخطأ في جهة التكلفة الأحوط (مراجعة المشرف على #137).
     * - والعدّ قبل المقارنة (hit ثم الموازنة)، فلا يتجاوز الحدَّ عاملان متزامنان. والرقم أولاً، فما رُدّ بحده لا يستهلك حد البوابة.
     */
    private function overLimit(string $phone): ?string
    {
        $mark = 'exam-receipt:counted:'.$this->submissionId.':'.($this->attemptId ?? 0);

        if (! MessagingSettings::ready(MessagingChannel::WHATSAPP) || ! isset(MessagingSettings::whatsapp()['templates']['exam_receipt'])
            || Cache::has($mark)) {
            return null;
        }

        $limit = $this->hitLimits($phone);

        // بعد العدّ كاملاً وحده: ما سقط قبل هذا السطر (ولو بعد عدّ الرقم) يُعدّ ثانية في إعادته
        Cache::put($mark, true, 86400);

        return $limit;
    }

    /**
     * يعدّ الإيصال في الحدين، ويعيد اسم أول حد تجاوزه أو null.
     */
    private function hitLimits(string $phone): ?string
    {

        // والصفر بلا حد، كحد الرقم في DeliverMessage
        $limits = [
            'number' => ['exam-receipt:number:'.LoginIdentifier::hash($phone), (int) config('messaging.exam_receipt_per_number_per_day')],
            'global' => ['exam-receipt:global', (int) config('messaging.exam_receipt_per_day')],
        ];

        foreach ($limits as $name => [$key, $max]) {
            if ($max > 0 && RateLimiter::hit($key, 86400) > $max) {
                return $name;
            }
        }

        return null;
    }
}
