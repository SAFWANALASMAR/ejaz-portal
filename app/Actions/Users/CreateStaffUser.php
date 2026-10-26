<?php

namespace App\Actions\Users;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * حساب موظف ينشئه المشرف من لوحة الإدارة. لا يكتب المشرف كلمة مرور:
 * تُولَّد عشوائية، ويصل صاحب الحساب بريد لتعيين كلمة مروره.
 */
class CreateStaffUser
{
    public function __construct(
        private ChangeUserRoles $changeRoles,
        private SendStaffInvitation $invite,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: ?string}  $data
     * @param  list<string>  $roles
     */
    public function handle(User $actor, array $data, array $roles): User
    {
        // الصلاحية تُفحص في الإجراء نفسه، فقد يُستدعى من غير شاشة الإدارة (الاستيراد مثلاً)
        if (! $actor->can('create', User::class)) {
            throw new AuthorizationException;
        }

        $user = DB::transaction(function () use ($actor, $data, $roles): User {
            // البريد يُنشأ موثقاً، فيفقده ما يحمله من حسابات غير موثقة (أحقية الموثق — User::markEmailAsVerified)
            User::releaseFromUnverified('email', mb_strtolower($data['email']));

            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => Str::password(32),
                'email_verified_at' => now(),
            ])->save();

            // الأدوار يمنحها من يملك منحها (RoleName::assignableBy)؛ وبدونها يبدأ الحساب مستفيداً
            if ($roles !== []) {
                $this->changeRoles->handle($actor, $user, $roles, newAccount: true);
            } else {
                $user->assignRole(RoleName::Member->value);
            }

            return $user;
        });

        $this->invite->handle($actor, $user);

        return $user;
    }
}
