<?php

namespace App\Actions\Assembly;

use App\Enums\MeetingStatus;
use App\Jobs\SyncOnlineMeeting;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingNotice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * إلغاء اجتماع مجدول (السكرتير)، ولا رجوع عنه: يُلغى اجتماع تيمز في الطابور إن وُجد، ويُبلَّغ الأعضاء
 * الفعّالون ما لم يحن موعده.
 */
class CancelMeeting
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Meeting $meeting): Meeting
    {
        if (! $actor->can('cancel', $meeting)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor, $meeting): void {
            /** @var Meeting $locked */
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();

            // انعقد أو أُلغي بين فتح الصفحة والطلب
            if (! $actor->can('cancel', $locked)) {
                throw new AuthorizationException;
            }

            $locked->status = MeetingStatus::Cancelled;
            $locked->save();
            $meeting->setRawAttributes($locked->getAttributes(), true);
        });

        if ($meeting->hasOnlineMeeting()) {
            SyncOnlineMeeting::dispatch($meeting->id);
        }

        if ($meeting->starts_at->isFuture()) {
            SaveMeeting::notifyMembers(new MeetingNotice($meeting, 'cancelled'));
        }

        return $meeting;
    }
}
