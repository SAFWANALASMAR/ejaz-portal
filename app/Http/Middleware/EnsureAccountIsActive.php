<?php

namespace App\Http\Middleware;

use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * الحساب الموقوف يخرج فوراً من جلسته المفتوحة، لا عند الدخول التالي فقط.
 *
 * وكذلك كل جلسة بدأت قبل إنهاء جلسات الحساب من اللوحة (sessions_revoked_at — ResetTwoFactorByAdmin)، أياً كان مخزن
 * الجلسات: وقت الدخول في الجلسة نفسها (LOGGED_IN_AT، من حدث الدخول في FortifyServiceProvider). والجلسة بلا وقت دخول بدأت
 * قبل هذا الحقل، فهي أقدم من كل إنهاء. والإنهاء في الثانية نفسها يشمل ما دخل فيها.
 */
class EnsureAccountIsActive
{
    public const LOGGED_IN_AT = 'auth_logged_in_at';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            return $this->logout($request, 'error', 'auth.deactivated');
        }

        $revokedAt = $user instanceof User ? $user->sessions_revoked_at : null;

        if ($revokedAt instanceof CarbonInterface) {
            $loggedInAt = $request->session()->get(self::LOGGED_IN_AT);

            if (! is_int($loggedInAt) || $loggedInAt <= $revokedAt->getTimestamp()) {
                return $this->logout($request, 'status', 'users.reset_two_factor.sessions_ended');
            }
        }

        return $next($request);
    }

    private function logout(Request $request, string $flash, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with($flash, __($message));
    }
}
