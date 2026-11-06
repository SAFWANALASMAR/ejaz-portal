<?php

namespace App\Http\Responses;

use App\Support\AccountSettings;
use App\Support\IntendedUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * بعد التسجيل: إلى توثيق الجوال متى كان التوثيق مفعّلاً من اللوحة، فقد أُرسل إليه الرمز، وإلا إلى رمز توثيق البريد (قرار
 * اجتماع 6 نوفمبر 2026 — decisions/2026-11-06-email-otp.md)، ومنه «لاحقاً» إلى «حسابي». ولا عودة إلى الصفحة التي جاء منها، ولو كانت التسجيل في مسابقة أو
 * برنامج: الرابط المقصود في الجلسة يُحذف، فلا يتبعه التوثيق بعده أيضاً، إلا رابط الاستبيان برمزه (IntendedUrl — قرار صفوان،
 * 5 نوفمبر 2026 — decisions/2026-11-05-phone-login.md).
 */
class RegisterResponse implements RegisterResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        IntendedUrl::keepOnlyFollowedAfterLogin($request);

        $user = $request->user();

        if (AccountSettings::verificationEnabled() && $user?->phoneToVerify() !== null) {
            return redirect()->route('account.phone.verify');
        }

        // وإلا إلى رمز البريد، فقد أُرسل بعد التسجيل (SendVerificationWhenRequired — decisions/2026-11-06-email-otp.md)
        return $user !== null && $user->email !== null && ! $user->hasVerifiedEmail()
            ? redirect()->route('verification.notice')
            : redirect()->intended(route('account.dashboard'));
    }
}
