<?php

namespace App\Actions\Users;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;

/**
 * دعوة صاحب الحساب إلى تعيين كلمة مروره: عند إنشاء الحساب من لوحة الإدارة، وإعادتها
 * ما دام لم يدخل بعد (إذا انتهت مدة الرابط مثلاً). الدعوة الجديدة تُبطل السابقة.
 */
class SendStaffInvitation
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $user): void
    {
        if (! $actor->can('invite', $user)) {
            throw new AuthorizationException;
        }

        /** @var PasswordBroker $broker */
        $broker = Password::broker();

        $user->notify(new StaffInvitation($broker->createToken($user)));

        activity('users')
            ->causedBy($actor)
            ->performedOn($user)
            ->event(ActivityEvent::InvitationSent->value)
            ->log(ActivityEvent::InvitationSent->value);
    }
}
