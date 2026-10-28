<?php

namespace App\Http\Responses;

use App\Support\AccountSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * بعد التسجيل: إلى توثيق الجوال متى كان التوثيق مفعّلاً وللحساب جوال، فقد أُرسل إليه الرمز. وإلا إلى لوحة المستخدم (التوثيق
 * مؤجل بقرار المدير التنفيذي، 25 أكتوبر 2026 — AccountSettings).
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

        return AccountSettings::verificationEnabled() && $request->user()?->phone !== null
            ? redirect()->route('account.phone.verify')
            : redirect()->route('account.dashboard');
    }
}
