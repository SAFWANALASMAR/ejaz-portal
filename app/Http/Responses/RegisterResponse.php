<?php

namespace App\Http\Responses;

use App\Support\AccountSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * بعد التسجيل: إلى توثيق الجوال متى كان التوثيق مفعّلاً، فقد أُرسل إليه الرمز، وإلا إلى «حسابي» (التوثيق مؤجل بقرار
 * المدير التنفيذي، 25 أكتوبر 2026 — AccountSettings). ولا عودة إلى الصفحة التي جاء منها، ولو كانت التسجيل في مسابقة أو
 * برنامج: الرابط المقصود في الجلسة يُحذف، فلا يتبعه التوثيق بعده أيضاً (قرار صفوان، 5 نوفمبر 2026 —
 * decisions/2026-11-05-phone-login.md).
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

        $request->session()->forget('url.intended');

        return AccountSettings::verificationEnabled() && $request->user()?->phone !== null
            ? redirect()->route('account.phone.verify')
            : redirect()->route('account.dashboard');
    }
}
