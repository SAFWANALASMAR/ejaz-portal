<?php

namespace App\Support;

use App\Models\User;
use Filament\Facades\Filament;

/**
 * نص من «محتوى الرئيسية» أصله علامة 【ناقص: …】 حتى تعتمده الجمعية (أمر التصور النهائي، النسخة 63). العلامة تظهر في التطوير
 * والبيئة التجريبية وحدهما كـ x-missing، ولا يرى زائر الإنتاج منها شيئاً: يُرجَع null فيُخفي القالب سطره.
 *
 * وعلى رابط العرض (HIDE_MISSING_MARKERS=true — ملاحظة صفوان ٣٠، 3 نوفمبر 2026) تختفي عن الزوار كالإنتاج، وتبقى لمن يدخل لوحة
 * الإدارة ليرى ما ينقص. والمحتوى المخزَّن مؤقتاً (ContentCache) بنسختين لذلك، فلا يرى الزائر نسخة الإدارة.
 */
final class MissingText
{
    public static function shown(?string $text): ?string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        return ! self::visible() && str_contains($text, '【') ? null : $text;
    }

    /**
     * تظهر العلامات خارج الإنتاج، إلا إن طُلب إخفاؤها عن الزوار فتظهر لمن يدخل لوحة الإدارة وحده.
     */
    public static function visible(): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        if (! config('app.hide_missing_markers')) {
            return true;
        }

        $user = auth()->user();

        return $user instanceof User && $user->canAccessPanel(Filament::getPanel('admin'));
    }
}
