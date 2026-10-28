<?php

namespace App\Http\Middleware;

use App\Contracts\BotChallenge;
use App\Services\BotChallenge\TurnstileChallenge;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * التحدي ضد الآليات (Turnstile) على نماذج الزائر: الدخول والتسجيل وطلب الاستعادة بالبريد والجوال (قرار صفوان، 20
 * أكتوبر 2026). يُتحقق من الرمز في الخادم قبل المتحكم، وبفعل النموذج نفسه، فلا يصلح رمز حُل لنموذج آخر، ولا يُقبل مرتين
 * (TurnstileChallenge). والمشغّل الوهمي في التطوير والاختبارات، ويُرفض في الإنتاج (AppServiceProvider)، كالاستبيان.
 * ومسارات Fortify يسجلها هو، فيُفحص هنا باسم المسار في مجموعة web.
 * والتحدي إذا اجتيز وTurnstile مضبوط بمفتاحيه عُلّم على الطلب، فيرفع قفل الدخول حدودَ الحساب والشبكة إلى سقفها بدل
 * الحجب (LoginRateLimiter، قرار صفوان). والوهمي بلا مفتاحين لا يُعلَّم.
 */
class VerifyBotChallenge
{
    public const PASSED = 'bot_challenge.passed';

    /**
     * اسم المسار ← فعل التحدي في نموذجه (components/bot-challenge).
     */
    private const ACTIONS = [
        'login.store' => 'login',
        'register.store' => 'register',
        'password.email' => 'forgot',
        'password.phone.send' => 'forgot',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $action = self::ACTIONS[(string) $request->route()?->getName()] ?? null;

        if ($action === null || ! $request->isMethod('POST') || $request->user() !== null) {
            return $next($request);
        }

        $token = $request->input('cf-turnstile-response');

        if (! app(BotChallenge::class)->passes(is_string($token) ? $token : '', $action)) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'cf-turnstile-response']))
                ->withErrors(['bot' => __('account.bot_challenge.failed')]);
        }

        if (TurnstileChallenge::configured()) {
            $request->attributes->set(self::PASSED, true);
        }

        return $next($request);
    }
}
