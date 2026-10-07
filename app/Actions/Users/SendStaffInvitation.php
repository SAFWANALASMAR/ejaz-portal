<?php

namespace App\Actions\Users;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * دعوة صاحب الحساب إلى تعيين كلمة مروره: عند إنشاء الحساب من لوحة الإدارة، وإعادتها
 * ما دام لم يدخل بعد (إذا انتهت مدة الرابط مثلاً). الدعوة الجديدة تُبطل السابقة،
 * ودعوة واحدة في الدقيقة للحساب، فلا يُغرق بريده بضغطات متكررة.
 */
class SendStaffInvitation
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException دعوة سابقة قبل أقل من دقيقة
     */
    public function handle(User $actor, User $user): void
    {
        if (! $actor->can('invite', $user)) {
            throw new AuthorizationException;
        }

        $key = 'staff-invitation:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ValidationException::withMessages(['invitation' => __('users.invitation.too_soon')]);
        }

        RateLimiter::hit($key, 60);

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
