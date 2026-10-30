<?php

namespace App\Jobs;

use App\Actions\Messaging\DeliverMessage;
use App\Contracts\WhatsAppNotifier;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionSubmission;
use App\Models\MessagingChannel;
use App\Services\Messaging\MessagingException;
use App\Services\WhatsApp\WhatsAppMessage;
use App\Support\Dates;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * إشعار واتساب بعد إرسال الاختبار، إلى الجوال الموثّق في الحساب: تأكيد الاستلام، وتاريخ الإرسال ووقته
 * بتوقيت الرياض، مختوماً باسم الجمعية. ولا إجابات ولا درجة. والمزوّد خلف WhatsAppNotifier: قالب معتمد
 * (exam_receipt) متغيراته بالترتيب: المسابقة، والتاريخ، والوقت، واسم الجمعية (docs/guides/messaging.md).
 */
class SendExamReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  int|null  $attemptId  محاولة في مسابقة الطلاب (SubmitAttempt): وقت تسليمها لا وقت التسجيل
     */
    public function __construct(public int $submissionId, public ?int $attemptId = null) {}

    public function handle(WhatsAppNotifier $whatsapp, DeliverMessage $deliver): void
    {
        $submission = CompetitionSubmission::query()->with(['user', 'competition'])->find($this->submissionId);
        $phone = $submission?->user?->phone;
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
}
