<?php

namespace App\Jobs;

use App\Contracts\OnlineMeetings;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * يطابق اجتماع تيمز مع حال الاجتماع عند التنفيذ: ينشئه لاجتماع عن بعد بلا رابط، أو يحدّث عنوانه وموعده،
 * أو يلغيه لاجتماع ملغى أو محذوف أو صار في القاعة. مهمتان لاجتماع واحد لا تتداخلان، فلا يُنشأ له اجتماعا
 * تيمز، وكل مهمة تقرأ الحال عند تنفيذها. وتُعاد المحاولة عند تعذر الشبكة ست ساعات.
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

    public function handle(OnlineMeetings $meetings): void
    {
        $meeting = Meeting::withTrashed()->find($this->meetingId);

        if ($meeting === null) {
            return;
        }

        $wanted = $meeting->is_online && $meeting->status !== MeetingStatus::Cancelled && ! $meeting->trashed();

        if (! $wanted) {
            if ($meeting->online_meeting_id !== null) {
                $meetings->cancel($meeting);
                $meeting->forceFill(['online_meeting_id' => null, 'online_join_url' => null])->saveQuietly();
            }

            return;
        }

        if ($meeting->online_meeting_id === null) {
            $created = $meetings->create($meeting);
            $meeting->forceFill(['online_meeting_id' => $created['id'], 'online_join_url' => $created['join_url']])->saveQuietly();

            return;
        }

        $meetings->update($meeting);
    }
}
