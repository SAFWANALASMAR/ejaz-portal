<?php

namespace App\Http\Responses;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\IntendedUrl;
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
 * (ملاحظات المدير التنفيذي، 25 أكتوبر 2026). وعضو مجلس الإدارة كالمستفيد إلى «حسابي»، وفيه بطاقات المجلس، ما لم يكن له دور
 * آخر في اللوحة (البند ٤، ليلة 5 نوفمبر 2026 — decisions/2026-11-06-board-portal.md).
 *
 * ولا عودة إلى أي صفحة سابقة، ولو كانت صفحة المسابقة أو البرنامج التي طلبت الدخول: الرابط المقصود في الجلسة يُحذف
 * (قرار صفوان، 5 نوفمبر 2026 — decisions/2026-11-05-phone-login.md، ويلغي العودة في قرار 30 أكتوبر). إلا رابط الاستبيان
 * برمزه ورابط توثيق البريد، فيعودان إليهما (IntendedUrl — مراجعة المشرف على #180، ٦).
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
        IntendedUrl::keepOnlyFollowedAfterLogin($request);

        // أدوار اللوحة سوى عضو المجلس: من له أحدها يبدأ في اللوحة
        $staffRoles = array_values(array_diff(RoleName::values(RoleName::panelRoles()), [RoleName::BoardMember->value]));

        if ($user instanceof User && $user->canAccessPanel(Filament::getPanel('admin'))
            && ! $user->hasRole(RoleName::SuperAdmin->value) && $user->hasAnyRole($staffRoles)) {
            return redirect()->intended(url(Filament::getPanel('admin')->getPath()));
        }

        // ومن يلزمه التحقق الثنائي (عضو المجلس) ولم يفعّله يبدأ في «الأمان» ليفعّله، كما كانت اللوحة تعيده إليه
        // (المراجعة الأمنية على #197)
        if ($user instanceof User && $user->requiresTwoFactor() && ! $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->forget('url.intended');

            return redirect()->route('account.security')->with('status', __('auth.two_factor_required'));
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
