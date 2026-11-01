<?php

namespace App\Support;

/**
 * نص من «محتوى الرئيسية» أصله علامة 【ناقص: …】 حتى تعتمده الجمعية (أمر التصور النهائي، النسخة 63). العلامة تظهر في التطوير
 * والبيئة التجريبية وحدهما كـ x-missing، ولا يرى زائر الإنتاج منها شيئاً: يُرجَع null فيُخفي القالب سطره.
 */
final class MissingText
{
    public static function shown(?string $text): ?string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        return app()->isProduction() && str_contains($text, '【') ? null : $text;
    }
}
