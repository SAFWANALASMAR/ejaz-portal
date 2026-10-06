<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * معالجة طلب حذف الحساب: تُخفى البيانات الشخصية ويوقف الحساب، ويبقى السجل نفسه
 * لما يلزم الاحتفاظ به نظاماً (الطلبات والتسجيلات المرتبطة به).
 * لا تراجع عنها، فلا تُنفَّذ إلا على حساب طلب صاحبه الحذف.
 */
class AnonymizeUser
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $user): void
    {
        // شروط الإجراء نفسه تُفحص صراحة، لأن Gate::before يمنح المشرف العام كل الصلاحيات
        if (! self::eligible($actor, $user) || ! $actor->can('anonymize', $user)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor, $user): void {
            $user->syncRoles([]);

            $user->forceFill([
                'name' => __('users.anonymized_name'),
                'email' => 'deleted-'.$user->id.'@deleted.invalid',
                'phone' => null,
                'email_verified_at' => null,
                'phone_verified_at' => null,
                'password' => Str::password(32),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
                'is_active' => false,
                'anonymized_at' => now(),
            ])->save();

            activity('users')
                ->causedBy($actor)
                ->performedOn($user)
                ->event('anonymized')
                ->log('anonymized');
        });
    }

    /**
     * حساب طلب صاحبه الحذف، ولم تُخفَ بياناته بعد، وليس حساب المنفّذ.
     */
    public static function eligible(User $actor, User $user): bool
    {
        return $actor->isNot($user)
            && $user->deletion_requested_at !== null
            && $user->anonymized_at === null;
    }
}
