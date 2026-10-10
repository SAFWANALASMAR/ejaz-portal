<?php

namespace App\Jobs;

use App\Contracts\WhatsAppNotifier;
use App\Models\CompetitionSubmission;
use App\Support\Dates;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * إشعار واتساب بعد إرسال الاختبار، إلى الجوال الموثّق في الحساب: تأكيد الاستلام، وتاريخ الإرسال ووقته
 * بتوقيت الرياض، مختوماً باسم الجمعية. ولا إجابات ولا درجة. والمزوّد خلف WhatsAppNotifier (قرار صفوان).
 */
class SendExamReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $submissionId) {}

    public function handle(WhatsAppNotifier $whatsapp): void
    {
        $submission = CompetitionSubmission::query()->with(['user', 'competition'])->find($this->submissionId);
        $phone = $submission?->user?->phone;

        if ($submission?->submitted_at === null || blank($phone)) {
            return;
        }

        $local = Dates::local($submission->submitted_at);

        $whatsapp->send((string) $phone, __('competitions.exam.whatsapp_message', [
            'competition' => $submission->competition->title,
            'date' => $local->format('Y-m-d'),
            'time' => $local->format('H:i'),
            'association' => __('competitions.exam.association'),
        ]));
    }
}
