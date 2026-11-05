<?php

namespace App\Actions\Users;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Notifications\PhoneNumberReleased;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * تحرير رقم جوال من حساب غير موثق (مراجعة المشرف على #180، المهمة ١ و٢ — decisions/2026-11-05-phone-login.md): الجوال فريد
 * على كل الحسابات، فمن سجّل برقم غيره حجزه عنه. يحرره مديرا النظام من «المستخدمون»، أو يأخذه صاحبه بالرمز حين يوثّقه
 * (VerifyPhoneCode). بسجل عملية، وتنبيه صاحب الحساب ببريده الموثق إن كان.
 */
class ReleasePhone
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $user): void
    {
        if (! $actor->can('releasePhone', $user)) {
            throw new AuthorizationException;
        }

        DB::transaction(fn () => self::release($user, $actor));
    }

    /**
     * ينزع الرقم غير الموثق من الحساب. يُستدعى داخل معاملة.
     */
    public static function release(User $holder, User $causer): void
    {
        $holder->forceFill(['phone' => null])->save();

        activity('security')
            ->causedBy($causer)
            ->performedOn($holder)
            ->event(ActivityEvent::PhoneReleased->value)
            ->log(ActivityEvent::PhoneReleased->value);

        if ($holder->hasVerifiedEmail()) {
            $holder->notify(new PhoneNumberReleased);
        }
    }
}
