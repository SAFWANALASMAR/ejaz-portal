<?php

namespace App\Contracts;

/**
 * التحدي ضد الآليات على إرسال غير المسجلين (الاستبيان العام): Cloudflare Turnstile في الإنتاج،
 * وتطبيق وهمي في التطوير والاختبارات (docs/decisions.md).
 */
interface BotChallenge
{
    /**
     * مفتاح الموقع لعنصر التحدي في الصفحة، أو null فلا يُعرض العنصر.
     */
    public function siteKey(): ?string;

    /**
     * يتحقق من رمز التحدي الذي أرسله المتصفح، في الخادم. وأي تعذر رفض لا قبول. والإجراء اسم النموذج
     * (data-action)، فلا يصلح رمز نموذج لنموذج آخر.
     */
    public function passes(string $token, string $action): bool;
}
