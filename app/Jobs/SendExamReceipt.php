<?php

namespace App\Jobs;

use App\Actions\Messaging\DeliverMessage;
use App\Contracts\WhatsAppNotifier;
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

    public function __construct(public int $submissionId) {}

    public function handle(WhatsAppNotifier $whatsapp, DeliverMessage $deliver): void
    {
        $submission = CompetitionSubmission::query()->with(['user', 'competition'])->find($this->submissionId);
        $phone = $submission?->user?->phone;

        if ($submission?->submitted_at === null || blank($phone)) {
            return;
        }

        $local = Dates::local($submission->submitted_at);

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
            if (! $exception->retryable) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }
}
