<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * الأدوار الإدارية الحساسة لا تدخل لوحة الإدارة قبل تفعيل التحقق الثنائي.
 * ولا بجلسة بدأت بكعكة «تذكّرني»: لا كلمة مرور فيها ولا تحدٍّ ثنائي. وكعكتها لا تصدر للأدوار الإدارية، فهي جلسة
 * استُعيدت قبل ترقية الحساب، فيُخرج ليدخل بكلمة المرور والتحقق الثنائي (مراجعة المشرف على الاختبار الأمني 0.14.0).
 */
class EnsureTwoFactorIsEnabled
{
    public const VIA_REMEMBER = 'auth_via_remember';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->requiresTwoFactor() && $request->session()->get(self::VIA_REMEMBER) === true) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', __('auth.sign_in_again'));
        }

        if ($user instanceof User && $user->requiresTwoFactor() && ! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('account.security')
                ->with('status', __('auth.two_factor_required'));
        }

        return $next($request);
    }
}
