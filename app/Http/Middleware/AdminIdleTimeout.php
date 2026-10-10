<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * تنتهي جلسة لوحة الإدارة بعد مدة خمول (config/security.php).
 */
class AdminIdleTimeout
{
    public const SESSION_KEY = 'admin_last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $lastActivity = $session->get(self::SESSION_KEY);
        $limit = config('security.admin_idle_minutes') * 60;

        if (is_int($lastActivity) && now()->getTimestamp() - $lastActivity > $limit) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('login')->with('status', __('auth.idle_logout'));
        }

        $session->put(self::SESSION_KEY, now()->getTimestamp());

        return $next($request);
    }
}
