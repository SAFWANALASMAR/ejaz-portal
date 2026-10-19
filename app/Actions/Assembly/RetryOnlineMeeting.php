<?php

namespace App\Actions\Assembly;

use App\Enums\ActivityEvent;
use App\Jobs\SyncOnlineMeeting;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * إعادة مزامنة اجتماع بتيمز من اللوحة بعد تعذرها (السكرتير — MeetingPolicy::syncOnline): يُمسح السبب
 * المعروض وتُعاد المهمة في الطابور، فتنشئ أو تحدّث أو تلغي بحال الاجتماع عند تنفيذها. وتُسجَّل.
 */
class RetryOnlineMeeting
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException اجتماع لا يحتاج مزامنة
     */
    public function handle(User $actor, Meeting $meeting): void
    {
        if (! $actor->can('syncOnline', $meeting)) {
            throw new AuthorizationException;
        }

        if (! $meeting->needsOnlineSync()) {
            throw ValidationException::withMessages(['meeting' => __('assembly.validation.online_in_sync')]);
        }

        activity('assembly')->causedBy($actor)->performedOn($meeting)
            ->event(ActivityEvent::OnlineMeetingRetried->value)
            ->withProperties(['reason' => $meeting->online_sync_error?->value])
            ->log(ActivityEvent::OnlineMeetingRetried->value);

        $meeting->forceFill(['online_sync_error' => null, 'online_sync_failed_at' => null])->saveQuietly();

        SyncOnlineMeeting::dispatch($meeting->id);
    }
}
