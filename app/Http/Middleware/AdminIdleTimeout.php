<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * تنتهي جلسة لوحة الإدارة بعد مدة خمول، وعند سقف مطلق مستقل عن النشاط (config/security.php).
 * والاستطلاع الآلي (wire:poll) لا يُعدّ نشاطاً، فلا تبقى اللوحة المتروكة مفتوحة.
 */
class AdminIdleTimeout
{
    public const SESSION_KEY = 'admin_last_activity';

    public const STARTED_KEY = 'admin_session_started';

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $now = now()->getTimestamp();
        $lastActivity = $session->get(self::SESSION_KEY);
        $startedAt = $session->get(self::STARTED_KEY);
        $idleLimit = config('security.admin_idle_minutes') * 60;
        $absoluteLimit = config('security.admin_session_max_minutes') * 60;

        if (is_int($startedAt) && $now - $startedAt > $absoluteLimit) {
            return $this->logout($request, 'auth.admin_session_expired');
        }

        if (is_int($lastActivity) && $now - $lastActivity > $idleLimit) {
            return $this->logout($request, 'auth.idle_logout');
        }

        if (! is_int($startedAt)) {
            $session->put(self::STARTED_KEY, $now);
        }

        if (! is_int($lastActivity) || ! $this->isAutomaticPoll($request)) {
            $session->put(self::SESSION_KEY, $now);
        }

        return $next($request);
    }

    private function logout(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __($message));
    }

    /**
     * طلب Livewire كل أفعاله استطلاع آلي ولا تحديث فيه لحقل: لا فعل من المستخدم.
     * وطلب `$commit` المرافق للاستطلاع (المكوّنات الفرعية المربوطة) لا يغيّر الحكم.
     * وقراءات واجهة Horizon استطلاع آلي كذلك: لوحته تطلبها كل ثوانٍ، فلا يبقى Horizon المتروك مفتوحاً. والنشاط فيه
     * تحميل صفحته أو إجراء (إعادة مهمة)، لا قراءة.
     */
    private function isAutomaticPoll(Request $request): bool
    {
        if ($request->isMethod('GET') && $request->is('horizon/api/*')) {
            return true;
        }

        if (! Livewire::isLivewireRequest()) {
            return false;
        }

        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        $hasPoll = false;

        foreach ($components as $component) {
            if (! is_array($component) || ! empty($component['updates'])) {
                return false;
            }

            foreach ((array) ($component['calls'] ?? []) as $call) {
                $type = is_array($call) ? ($call['metadata']['type'] ?? null) : null;

                if ($type === 'poll') {
                    $hasPoll = true;
                } elseif (! is_array($call) || ($call['method'] ?? null) !== '$commit') {
                    return false;
                }
            }
        }

        return $hasPoll;
    }
}
