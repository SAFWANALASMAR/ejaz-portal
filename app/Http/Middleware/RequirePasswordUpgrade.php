<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حساب قائم كلمة مروره لا تطابق السياسة الجديدة (PasswordPolicy) يُطالَب بتغييرها: علامة في الجلسة يضعها الدخول
 * (FortifyServiceProvider::authenticate)، فلا يُفتح إلا «بياناتي» لتغييرها وما يلزمه والخروج، في الموقع واللوحة.
 * وتُرفع العلامة بعد التغيير (UpdateUserPassword). فلا تُكسر الحسابات القائمة، ولا تبقى على كلمة مرور ضعيفة
 * (قرار صفوان، 20 أكتوبر 2026).
 */
class RequirePasswordUpgrade
{
    public const SESSION_KEY = 'auth.password_upgrade';

    /**
     * ما يُفتح والمطالبة قائمة: «بياناتي» وفيها نموذج تغيير كلمة المرور، وتغييرها، و«الأمان» وتأكيد كلمة المرور
     * والتحقق الثنائي فيه (شرط اللوحة)، والخروج.
     */
    private const ALLOWED = ['account.profile', 'user-password.update', 'account.security', 'password.confirm', 'password.confirm.store', 'password.confirmation', 'two-factor.*', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->get(self::SESSION_KEY) || $request->user() === null || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        return redirect()->to(route('account.profile').'#password')->with('status', __('account.password_rules.upgrade_required'));
    }
}
