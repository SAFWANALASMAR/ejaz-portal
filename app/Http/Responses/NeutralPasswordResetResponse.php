<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Http\Responses\FailedPasswordResetResponse;

/**
 * فشل تعيين كلمة المرور برسالة «الرابط غير صالح» وحدها، سواء وُجد البريد أم لا. كان البريد غير المسجل يرد برسالة
 * أخرى، فيكشف نموذج كلمة المرور الجديدة برمز خاطئ الحسابات المسجلة (الاختبار الأمني 0.14.0).
 */
class NeutralPasswordResetResponse extends FailedPasswordResetResponse
{
    public function __construct(string $status)
    {
        parent::__construct(Password::INVALID_TOKEN);
    }
}
