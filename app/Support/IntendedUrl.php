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

        // ولا رابط على غير أصل الطلب نفسه: redirect()->guest() يحفظ الـ Referer كما هو لطلب ليس GET (ومنه HEAD)، فيُحذف
        // رابط خارجي لا يُتبع (المراجعة الأمنية على feature/programs-how-to، 30 أكتوبر 2026)
        $parts = parse_url($intended);
        $origin = is_array($parts) && isset($parts['scheme'], $parts['host'])
            ? strtolower($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''))
            : null;

        if ($origin !== strtolower($request->getSchemeAndHttpHost())) {
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
}
