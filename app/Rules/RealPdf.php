<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * ملف PDF حقيقي (requirements/01 — قسم 4): يبدأ بتوقيع %PDF-، ونوعه من محتواه application/pdf،
 * وبلا محتوى نشط: سكربت، أو أمر تشغيل، أو ملفات مضمّنة، أو وسائط تفاعلية. وتُفك الأسماء
 * المرمّزة (/J#61vaScript) قبل البحث. والمحتوى داخل التدفقات المضغوطة لا يُفحص، فهذا خط
 * دفاع أول لا ضمان كامل، ويبقى العرض في متصفح الزائر.
 */
class RealPdf implements ValidationRule
{
    private const ACTIVE = ['/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/RichMedia'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $problem = $value instanceof UploadedFile ? self::problem((string) $value->get()) : 'validation.real_pdf';

        if ($problem !== null) {
            $fail($problem)->translate();
        }
    }

    /**
     * مفتاح رسالة الخطأ، أو null إن كان المحتوى PDF مقبولاً.
     */
    public static function problem(string $contents): ?string
    {
        if (! str_starts_with($contents, '%PDF-') || (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) !== 'application/pdf') {
            return 'validation.real_pdf';
        }

        // الأسماء في PDF قد تُرمَّز بـ #XX لإخفائها
        $names = (string) preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn (array $match): string => chr((int) hexdec($match[1])), $contents);

        foreach (self::ACTIVE as $name) {
            if (preg_match('#'.preg_quote($name, '#').'(?![A-Za-z0-9])#', $names) === 1) {
                return 'validation.pdf_active_content';
            }
        }

        return null;
    }
}
