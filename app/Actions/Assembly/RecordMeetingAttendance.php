<?php

namespace App\Actions\Assembly;

use App\Enums\ActivityEvent;
use App\Models\AssemblyMembership;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تسجيل حضور اجتماع بعد بدء موعده (السكرتير): الحاضرون من الأعضاء الفعّالين وحدهم، والتسجيل الجديد
 * يستبدل السابق. ويُحفظ عدد الأعضاء الفعّالين عند التسجيل، فلا يتغير النصاب بتغيّر السجل بعده.
 */
class RecordMeetingAttendance
{
    /**
     * @param  list<int|string>  $userIds
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Meeting $meeting, array $userIds): Meeting
    {
        if (! $actor->can('recordAttendance', $meeting)) {
            throw new AuthorizationException;
        }

        $ids = array_values(array_unique(array_map('intval', $userIds)));

        if (AssemblyMembership::activeUsers()->whereKey($ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['attendees' => __('assembly.validation.not_active_member')]);
        }

        DB::transaction(function () use ($actor, $meeting, $ids): void {
            /** @var Meeting $locked */
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();

            if (! $actor->can('recordAttendance', $locked)) {
                throw new AuthorizationException;
            }

            $locked->attendees()->syncWithPivotValues($ids, ['recorded_by' => $actor->id, 'created_at' => now()]);
            $locked->members_count = AssemblyMembership::activeUsers()->count();
            $locked->save();

            activity('assembly')->causedBy($actor)->performedOn($locked)
                ->event(ActivityEvent::AttendanceRecorded->value)
                ->withProperties(['present' => count($ids), 'members' => $locked->members_count])
                ->log(ActivityEvent::AttendanceRecorded->value);

            $meeting->setRawAttributes($locked->getAttributes(), true);
        });

        return $meeting;
    }
}
