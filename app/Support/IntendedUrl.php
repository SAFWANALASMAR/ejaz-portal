<?php

namespace App\Support;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * الرابط المقصود قبل الدخول (url.intended في الجلسة) لا يُتبع إلى لوحة الإدارة لمن لا يدخلها: خرج مسؤول من المتصفح وبقي
 * رابط اللوحة في الجلسة، ثم دخل مستفيد فحُوِّل إليه فرأى 403 «لا تملك صلاحية الوصول» (ملاحظة المدير التنفيذي «حساب المستفيد
 * لا يفتح»، 29 أكتوبر 2026). فيُحذف، ويذهب إلى مدخل بوابته.
 */
final class IntendedUrl
{
    public static function dropAdminFor(Request $request, ?User $user): void
    {
        $intended = $request->session()->get('url.intended');

        if (! is_string($intended)) {
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
