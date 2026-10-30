<?php

namespace App\Support;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * الرابط المقصود قبل الدخول (url.intended في الجلسة) لا يُتبع إلى لوحة الإدارة لمن لا يدخلها: خرج مسؤول من المتصفح وبقي
 * رابط اللوحة في الجلسة، ثم دخل مستفيد فحُوِّل إليه فرأى 403 «لا تملك صلاحية الوصول» (ملاحظة المدير التنفيذي «حساب المستفيد
 * لا يفتح»، 29 أكتوبر 2026). فيُحذف، ويذهب إلى مدخل بوابته. وكذلك رابط على غير أصل الطلب.
 */
final class IntendedUrl
{
    public static function dropAdminFor(Request $request, ?User $user): void
    {
        $intended = $request->session()->get('url.intended');

        if (! is_string($intended)) {
            return;
        }

        // ولا رابط على غير أصل البوابة: redirect()->guest() يحفظ الـ Referer كما هو لطلب ليس GET (ومنه HEAD)، فيُحذف رابط
        // خارجي لا يُتبع (المراجعة الأمنية على feature/programs-how-to، 30 أكتوبر 2026). والأصل أصل الطلب أو أصل APP_URL،
        // لأن route() يُبنى من APP_URL ولو وصل الطلب من مضيف آخر (موزع أحمال أو 127.0.0.1 — مراجعة المشرف على #100).
        // وما فيه شرطة مائلة عكسية أو محرف تحكم يُرفض قبل تحليله، فلا يقرؤه المتصفح بغير ما قرأه parse_url
        $origin = preg_match('/[\\\\\x00-\x1F\x7F]/', $intended) === 1 ? null : self::origin($intended);

        if ($origin === null || ! in_array($origin, [self::origin($request->getSchemeAndHttpHost()), self::origin(url('/'))], true)) {
            $request->session()->forget('url.intended');

            return;
        }

        $panel = Filament::getPanel('admin');
        $prefix = '/'.trim($panel->getPath(), '/');
        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');

        $inPanel = $path === $prefix || str_starts_with($path, $prefix.'/');

        if ($inPanel && ! ($user instanceof User && $user->canAccessPanel($panel))) {
            $request->session()->forget('url.intended');
        }
    }

    /**
     * أصل الرابط (المخطط والمضيف والمنفذ) بحروف صغيرة، أو null لرابط بلا مخطط أو مضيف.
     */
    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);

        return is_array($parts) && isset($parts['scheme'], $parts['host'])
            ? strtolower($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''))
            : null;
    }
}
