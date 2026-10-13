<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * الأدوار الإدارية الحساسة لا تدخل لوحة الإدارة قبل تفعيل التحقق الثنائي.
 */
class EnsureTwoFactorIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->requiresTwoFactor() && ! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('account.security')
                ->with('status', __('auth.two_factor_required'));
        }

        return $next($request);
    }
}
