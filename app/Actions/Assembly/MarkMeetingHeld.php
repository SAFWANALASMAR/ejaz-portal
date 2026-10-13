<?php

namespace App\Actions\Assembly;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * تسجيل انعقاد اجتماع مجدول بعد بدء موعده (السكرتير)، فيُرفع محضره بعدها.
 */
class MarkMeetingHeld
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Meeting $meeting): Meeting
    {
        if (! $actor->can('markHeld', $meeting)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor, $meeting): void {
            /** @var Meeting $locked */
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();

            if (! $actor->can('markHeld', $locked)) {
                throw new AuthorizationException;
            }

            $locked->status = MeetingStatus::Held;
            $locked->save();
            $meeting->setRawAttributes($locked->getAttributes(), true);
        });

        return $meeting;
    }
}
