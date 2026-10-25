<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حساب قائم كلمة مروره لا تطابق السياسة الجديدة (PasswordPolicy) يُطالَب بتغييرها: علامة على الحساب
 * (password_upgrade_required_at) يضعها الدخول بكلمة المرور (FortifyServiceProvider::authenticate)، فلا يُفتح إلا «بياناتي»
 * لتغييرها وما يلزمه والخروج، في الموقع واللوحة. وتُرفع بعد التغيير أو الاستعادة (UpdateUserPassword وResetUserPassword).
 * فلا تُكسر الحسابات القائمة، ولا تبقى على كلمة مرور ضعيفة (قرار صفوان، 20 أكتوبر 2026).
 *
 * والعلامة على الحساب لا في الجلسة (مراجعة المشرف على #64، 25 أكتوبر 2026): تبقى مع دخول «تذكّرني» وجلساته الأخرى، ولا
 * تنتقل إلى حساب آخر يدخل في الجلسة نفسها. والحساب الذي لم يدخل بكلمة مروره منذ النشر لا تُعرف قوتها، فيُطالَب عند أول
 * دخول بها (decisions.md).
 */
class RequirePasswordUpgrade
{
    /**
     * ما يُفتح والمطالبة قائمة: «بياناتي» وفيها نموذج تغيير كلمة المرور، وتغييرها، و«الأمان» وتأكيد كلمة المرور
     * والتحقق الثنائي فيه (شرط اللوحة)، والخروج.
     */
    private const ALLOWED = ['account.profile', 'user-password.update', 'account.security', 'password.confirm', 'password.confirm.store', 'password.confirmation', 'two-factor.*', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->password_upgrade_required_at === null || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        return redirect()->to(route('account.profile').'#password')->with('status', __('account.password_rules.upgrade_required'));
    }
}
