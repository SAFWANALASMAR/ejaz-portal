<?php

namespace App\Actions\Accounts;

use App\Models\User;
use App\Rules\SaudiMobile;
use App\Support\SaudiPhone;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * طلب رمز استعادة كلمة المرور بالجوال (قرار صفوان، 20 أكتوبر 2026). الرمز إلى رقم موثق في حساب نشط وحده، وبحدود رمز
 * التوثيق نفسها (SendPhoneVerificationCode: لكل حساب، ولكل رقم، ولكل شبكة، وسقف البوابة).
 *
 * والرد واحد وُجد الحساب أم لا، وبلغ حداً أم لا: لا تعداد للحسابات من الاستعادة. وما يُرد خطأً هو شكل الرقم وحده،
 * لأنه لا يكشف شيئاً عن الحسابات.
 */
class RequestPasswordResetCode
{
    public function __construct(private SendPhoneVerificationCode $send) {}

    /**
     * @return string الرقم موحّداً، لتعرضه صفحة إدخال الرمز
     *
     * @throws ValidationException شكل الرقم وحده
     */
    public function handle(mixed $phone): string
    {
        Validator::make(['phone' => $phone], ['phone' => ['required', 'string', new SaudiMobile]])->validate();

        $normalized = (string) SaudiPhone::normalize((string) $phone);

        $user = User::query()
            ->where('phone', $normalized)
            ->whereNotNull('phone_verified_at')
            ->where('is_active', true)
            ->first();

        if ($user !== null) {
            try {
                $this->send->handle($user, SendPhoneVerificationCode::RESET);
            } catch (ValidationException $limited) {
                // حد بُلغ: لا يُكشف للطالب، فيبقى الرد واحداً
                Log::info('Password reset code not sent.', ['user_id' => $user->id, 'reason' => array_key_first($limited->errors())]);
            }
        }

        return $normalized;
    }
}
