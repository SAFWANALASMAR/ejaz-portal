<?php

namespace App\Actions\Assembly;

use App\Enums\ActivityEvent;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * اعتماد مسودة المحضر (عضو المجلس، ولا يعتمدها من رفعها — MeetingPolicy::approveMinutes). بعده يظهر
 * المحضر للأعضاء، ولا يُستبدل ولا يُحذف.
 */
class ApproveMeetingMinutes
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Meeting $meeting): Meeting
    {
        if (! $actor->can('approveMinutes', $meeting)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor, $meeting): void {
            /** @var Meeting $locked */
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();

            // استُبدلت المسودة أو اعتُمدت بين فتح الصفحة والطلب
            if (! $actor->can('approveMinutes', $locked) || $locked->minutes_file !== $meeting->minutes_file) {
                throw new AuthorizationException;
            }

            $locked->forceFill(['minutes_approved_by' => $actor->id, 'minutes_approved_at' => now()])->save();

            activity('assembly')->causedBy($actor)->performedOn($locked)
                ->event(ActivityEvent::MinutesApproved->value)
                ->log(ActivityEvent::MinutesApproved->value);

            $meeting->setRawAttributes($locked->getAttributes(), true);
        });

        return $meeting;
    }
}
