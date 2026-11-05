<?php

namespace App\Http\Responses;

use App\Enums\RoleName;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * مدخل كل بوابة بعد الدخول (requirements/12 §12): من يدخل لوحة الإدارة (الموظفون والمجلس والمحكّمون)
 * إلى /admin، والمشترك إلى لوحته (/account). والمشرف العام إلى لوحته في حسابه، وفيها رابط لوحة الإدارة وإحصائياته
 * (ملاحظات المدير التنفيذي، 25 أكتوبر 2026).
 *
 * ولا عودة إلى أي صفحة سابقة، ولو كانت صفحة المسابقة أو البرنامج التي طلبت الدخول: الرابط المقصود في الجلسة يُحذف
 * (قرار صفوان، 5 نوفمبر 2026 — decisions/2026-11-05-phone-login.md، ويلغي العودة في قرار 30 أكتوبر).
 */
class PortalLoginResponse implements LoginResponse, TwoFactorLoginResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            // كاستجابتي Fortify: الدخول يخبر أن لا تحقق ثنائياً معلقاً، وإتمام التحقق الثنائي بلا محتوى
            return $request->hasAny(['code', 'recovery_code']) ? new JsonResponse('', 204) : new JsonResponse(['two_factor' => false]);
        }

        $user = $request->user();
        $request->session()->forget('url.intended');

        return redirect()->to($user instanceof User && $user->canAccessPanel(Filament::getPanel('admin')) && ! $user->hasRole(RoleName::SuperAdmin->value)
            ? url(Filament::getPanel('admin')->getPath())
            : Fortify::redirects('login'));
    }
}
