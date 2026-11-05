<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\AccountSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تعديل حساب من لوحة الإدارة. لا يوقف أحد حسابه هو، وتغيير البريد أو الجوال يُسقط توثيقه،
 * والأدوار لا تتغير إلا عبر ChangeUserRoles. والبريد والجوال اختياريان (ملاحظات المدير التنفيذي، 25 أكتوبر 2026).
 */
class UpdateUserAccount
{
    public function __construct(private ChangeUserRoles $changeRoles, private ChangeNationalIdExport $changeNationalIdExport) {}

    /**
     * @param  array{name: string, email?: ?string, phone?: ?string, is_active?: bool}  $data
     * @param  list<string>|null  $roles  null = لا تغيير في الأدوار
     * @param  bool|null  $nationalIdExport  تصدير رقم الهوية كاملاً ممنوحاً مباشرة، وnull = لا تغيير
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, User $user, array $data, ?array $roles = null, ?bool $nationalIdExport = null): void
    {
        // الحساب الذي أُخفيت بياناته لا يُعدَّل ولا يُعاد تفعيله (منع صريح في السياسة أيضاً)
        if ($user->anonymized_at !== null || ! $actor->can('update', $user)) {
            throw new AuthorizationException;
        }

        $isActive = $data['is_active'] ?? $user->is_active;
        $email = filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null;
        $emailChanged = $email !== $user->email;

        // حماية النفس تُفحص صراحة في الإجراء أيضاً (دفاع إضافي فوق السياسة)
        if ($actor->is($user) && ! $isActive) {
            throw new AuthorizationException(__('users.cannot_deactivate_self'));
        }

        $phone = filled($data['phone'] ?? null) ? $data['phone'] : null;

        // معرّف دخول واحد على الأقل يبقى، فلا يُغلق الحساب نهائياً بإفراغ البريد والجوال ممن لا اسم مستخدم له
        // (مراجعة المشرف المبكرة، ٢)
        if ($user->username === null && $email === null && $phone === null) {
            throw ValidationException::withMessages(['email' => __('users.identifier_required')]);
        }

        DB::transaction(function () use ($actor, $user, $data, $roles, $nationalIdExport, $isActive, $email, $phone): void {

            // البريد أو الجوال الجديد لا يرث توثيق القديم، وتوثيق الجوال أساس التقديم في المسابقات
            try {
                $user->forceFill([
                    'name' => $data['name'],
                    'email' => $email,
                    'phone' => $phone,
                    'is_active' => $isActive,
                    'email_verified_at' => $email === $user->email ? $user->email_verified_at : null,
                    'phone_verified_at' => $phone === $user->phone ? $user->phone_verified_at : null,
                    // رقم يُحفظ يُسقط الرقم المعلّق، فلا يُنزع برمزه القديم رقم غيره بعدُ (مراجعة المشرف، L2)
                    ...($phone !== null ? ['pending_phone' => null] : []),
                ])->save();
            } catch (UniqueConstraintViolationException $exception) {
                // حفظ متزامن بقيمة أخذها حساب آخر بعد فحص النموذج: خطأ التحقق نفسه لا 500، على حقل الفهرس الذي تعارض
                // (decisions/2026-11-05-phone-login.md)
                $field = str_contains($exception->getMessage(), 'email') ? 'email' : 'phone';

                throw ValidationException::withMessages([$field => __('validation.unique', ['attribute' => __('users.fields.'.$field)])]);
            }

            if ($roles !== null && $actor->can('assignRoles', $user)) {
                $this->changeRoles->handle($actor, $user, $roles);
            }

            // بعد الأدوار، فلا يُمنح لمن صار مشرفاً عاماً في الحفظ نفسه
            if ($nationalIdExport !== null) {
                $this->changeNationalIdExport->handle($actor, $user, $nationalIdExport);
            }

            // والحساب المعطّل لا يبقى له، فلا يعود صامتاً بإعادة تفعيله (مراجعة المشرف)
            if (! $isActive) {
                ChangeNationalIdExport::drop($actor, $user, 'deactivated');
            }
        });

        // البريد الجديد يُوثَّق متى كان التوثيق مفعّلاً من اللوحة؛ وتعديل الاسم مثلاً لا يعيد إرسال التوثيق
        if ($emailChanged && $email !== null && AccountSettings::verificationEnabled()) {
            $user->sendEmailVerificationNotification();
        }
    }
}
