<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Support\AccountSettings;
use App\Support\IntendedUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * بعد التسجيل: إلى توثيق الجوال متى كان التوثيق مفعّلاً وللحساب جوال، فقد أُرسل إليه الرمز، والوجهة باقية في الجلسة
 * يتبعها التوثيق بعده (PhoneVerificationController). وإلا إلى الصفحة التي جاء منها (التسجيل في مسابقة أو برنامج — ملاحظة
 * المراجعة على #96)، ولوحة المستخدم إن لم تكن (التوثيق مؤجل بقرار المدير التنفيذي، 25 أكتوبر 2026 — AccountSettings).
 * ولا يُتبع رابط مقصود في لوحة الإدارة لمن لا يدخلها (IntendedUrl).
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

        $user = $request->user();
        IntendedUrl::dropAdminFor($request, $user instanceof User ? $user : null);

        return AccountSettings::verificationEnabled() && $user?->phone !== null
            ? redirect()->route('account.phone.verify')
            : redirect()->intended(route('account.dashboard'));
    }
}
