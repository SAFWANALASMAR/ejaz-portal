<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * تعديل حساب من لوحة الإدارة. لا يوقف أحد حسابه هو، وتغيير البريد أو الجوال يُسقط توثيقه،
 * والأدوار لا تتغير إلا عبر ChangeUserRoles (للمشرف العام).
 */
class UpdateUserAccount
{
    public function __construct(private ChangeUserRoles $changeRoles) {}

    /**
     * @param  array{name: string, email: string, phone?: ?string, is_active?: bool}  $data
     * @param  list<string>|null  $roles  null = لا تغيير في الأدوار
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $user, array $data, ?array $roles = null): void
    {
        // الحساب الذي أُخفيت بياناته لا يُعدَّل ولا يُعاد تفعيله (منع صريح في السياسة أيضاً)
        if ($user->anonymized_at !== null || ! $actor->can('update', $user)) {
            throw new AuthorizationException;
        }

        $isActive = $data['is_active'] ?? $user->is_active;
        $emailChanged = mb_strtolower($data['email']) !== $user->email;

        // حماية النفس تُفحص صراحة في الإجراء أيضاً (دفاع إضافي فوق السياسة)
        if ($actor->is($user) && ! $isActive) {
            throw new AuthorizationException(__('users.cannot_deactivate_self'));
        }

        DB::transaction(function () use ($actor, $user, $data, $roles, $isActive): void {
            $email = mb_strtolower($data['email']);
            $phone = $data['phone'] ?? null;

            // البريد أو الجوال الجديد لا يرث توثيق القديم، وتوثيق الجوال أساس التقديم في المسابقات
            $user->forceFill([
                'name' => $data['name'],
                'email' => $email,
                'phone' => $phone,
                'is_active' => $isActive,
                'email_verified_at' => $email === $user->email ? $user->email_verified_at : null,
                'phone_verified_at' => $phone === $user->phone ? $user->phone_verified_at : null,
            ])->save();

            if ($roles !== null && $actor->can('assignRoles', $user)) {
                $this->changeRoles->handle($actor, $user, $roles);
            }
        });

        // البريد الجديد يُوثَّق؛ وتعديل الاسم مثلاً لا يعيد إرسال التوثيق
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }
    }
}
