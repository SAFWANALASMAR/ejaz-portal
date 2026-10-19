<?php

namespace App\Jobs;

use App\Contracts\OnlineMeetings;
use App\Enums\ActivityEvent;
use App\Enums\OnlineMeetingFailure;
use App\Exceptions\OnlineMeetingFailed;
use App\Models\Meeting;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يطابق اجتماع تيمز مع حال الاجتماع عند التنفيذ: ينشئه لاجتماع عن بعد بلا رابط، أو يحدّث عنوانه وموعده،
 * أو يلغيه لاجتماع ملغى أو محذوف أو صار في القاعة. مهمتان لاجتماع واحد لا تتداخلان، فلا يُنشأ له اجتماعا
 * تيمز، وكل مهمة تقرأ الحال عند تنفيذها.
 *
 * والتعذر لا يمس الاجتماع المحفوظ: يُحفظ سببه عليه فيظهر للمسؤول مع إعادة المحاولة، ويُسجَّل. والعابر
 * (مهلة، ضغط، عطل عند Microsoft) يُعاد آلياً ست ساعات، وغيره (ربط مطفأ، مفتاح مرفوض، صلاحية) ينتظر تصحيحه.
 */
class SyncOnlineMeeting implements ShouldQueue
{
    use Queueable;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public int $meetingId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->meetingId))->releaseAfter(30)->expireAfter(300)];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(): void
    {
        $meeting = Meeting::withTrashed()->find($this->meetingId);

        if ($meeting === null) {
            return;
        }

        try {
            $this->sync($meeting);
        } catch (OnlineMeetingFailed $failed) {
            self::recordFailure($meeting, $failed->reason, $failed->status);

            if ($failed->reason->isTransient()) {
                throw $failed;
            }

            return;
        }

        if ($meeting->online_sync_error !== null) {
            $meeting->forceFill(['online_sync_error' => null, 'online_sync_failed_at' => null])->saveQuietly();
        }
    }

    /**
     * بعد آخر محاولة: ما لم يُحفظ سببه (خطأ غير متوقع) يظهر للمسؤول كذلك.
     */
    public function failed(Throwable $exception): void
    {
        $meeting = Meeting::withTrashed()->find($this->meetingId);

        if ($meeting !== null && ! $exception instanceof OnlineMeetingFailed) {
            self::recordFailure($meeting, OnlineMeetingFailure::Unexpected, null);
        }
    }

    private function sync(Meeting $meeting): void
    {
        // لا شيء في تيمز ولا يلزم شيء: لا يُقرأ الإعداد أصلاً، فلا يتعذر اجتماع في القاعة
        if (! $meeting->wantsOnlineMeeting() && $meeting->online_meeting_id === null) {
            return;
        }

        $meetings = app(OnlineMeetings::class);

        if (! $meeting->wantsOnlineMeeting()) {
            $meetings->cancel($meeting);
            self::forget($meeting);

            return;
        }

        if ($meeting->online_meeting_id !== null) {
            try {
                $meetings->update($meeting);

                return;
            } catch (OnlineMeetingFailed $failed) {
                // حُذف الحدث من التقويم مباشرة: يُنشأ من جديد برابط جديد
                if ($failed->reason !== OnlineMeetingFailure::EventNotFound) {
                    throw $failed;
                }

                self::forget($meeting);
            }
        }

        $created = $meetings->create($meeting);
        $meeting->forceFill(['online_meeting_id' => $created['id'], 'online_join_url' => $created['join_url'], 'online_organizer' => $created['organizer']])->saveQuietly();
    }

    /**
     * زال اجتماع تيمز: يُنسى، ويتقدم الجيل فيُنشأ التالي بمعرّف منع تكرار جديد لا يعيد الزائل.
     */
    private static function forget(Meeting $meeting): void
    {
        $meeting->forceFill([
            'online_meeting_id' => null,
            'online_join_url' => null,
            'online_organizer' => null,
            'online_generation' => $meeting->online_generation + 1,
        ])->saveQuietly();
    }

    /**
     * السبب على الاجتماع، وفي سجل التطبيق، وفي سجل العمليات عند تغيّره (لا عند كل محاولة). بلا رد Microsoft.
     */
    private static function recordFailure(Meeting $meeting, OnlineMeetingFailure $reason, ?int $status): void
    {
        $changed = $meeting->online_sync_error !== $reason;
        $meeting->forceFill(['online_sync_error' => $reason, 'online_sync_failed_at' => now()])->saveQuietly();

        Log::warning('Teams meeting sync failed.', ['meeting' => $meeting->id, 'reason' => $reason->value, 'status' => $status]);

        if ($changed) {
            activity('assembly')->performedOn($meeting)
                ->event(ActivityEvent::OnlineMeetingFailed->value)
                ->withProperties(['reason' => $reason->value, 'status' => $status])
                ->log(ActivityEvent::OnlineMeetingFailed->value);
        }
    }
}
