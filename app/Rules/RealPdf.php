<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * ملف PDF حقيقي (requirements/01 — قسم 4): يبدأ بتوقيع %PDF-، ونوعه من محتواه application/pdf،
 * وبلا محتوى نشط: سكربت، أو أمر تشغيل، أو ملفات مضمّنة، أو وسائط تفاعلية، أو نماذج ترسل
 * بيانات. يُفحص النص الخام والتدفقات المضغوطة بعد فكّها (تدفقات الكائنات في PDF 1.5 فما فوق)
 * بسقف للحجم، وتُفك الأسماء المرمّزة (/J#61vaScript) قبل البحث. وهذا خط دفاع أول لا ضمان كامل
 * (docs/security.md)، والعرض في متصفح الزائر معزول.
 */
class RealPdf implements ValidationRule
{
    private const ACTIVE = ['/JavaScript', '/Launch', '/EmbeddedFile', '/RichMedia', '/SubmitForm', '/ImportData'];

    /**
     * مفتاحان قصيران يقعان مصادفةً في بيانات الصور والخطوط الثنائية المضغوطة (/JS ثلاثة بايتات، فيرفض كتباً سليمة كبيرة):
     * لا يُعدّان إلا وبعدهما قيمة كما في القاموس، بعد فراغات PDF أو تعليق: نص أو سلسلة ست عشرية أو مرجع كائن لـ /JS، ومصفوفة
     * أو مرجع لـ /XFA. وكل إجراء JavaScript يحمل /S /JavaScript الذي يُفحص بلا شرط
     * (docs/decisions/2026-11-13-fix-real-pdf-false-positives.md).
     */
    private const VALUED = [
        '/JS' => '\(|<|\d+[\x00\t\n\f\r ]+\d+[\x00\t\n\f\r ]+R',
        '/XFA' => '\[|\d+[\x00\t\n\f\r ]+\d+[\x00\t\n\f\r ]+R',
    ];

    /**
     * سقف ما يُفك من التدفقات، فلا يُستنزف الخادم بملف مضغوط مضخَّم.
     */
    private const MAX_INFLATED_BYTES = 50 * 1024 * 1024;

    /**
     * لا يُقرأ ملف أكبر من هذا في الذاكرة: قواعد الحقل بلا bail فيُستدعى الفحص ولو تجاوز الملف حده، وحد
     * الرفع المؤقت في اللوحة صار أكبر من حدود ملفات PDF (أكبرها 20 ميجابايت).
     */
    public const MAX_FILE_BYTES = 25 * 1024 * 1024;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $problem = $value instanceof UploadedFile && $value->getSize() <= self::MAX_FILE_BYTES
            ? self::problem((string) $value->get())
            : 'validation.real_pdf';

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
        $names = (string) preg_replace_callback(
            '/#([0-9A-Fa-f]{2})/',
            fn (array $match): string => chr((int) hexdec($match[1])),
            $contents."\n".self::inflatedStreams($contents),
        );

        // وخطأ البحث (false) رفضٌ لا قبول
        foreach (self::ACTIVE as $name) {
            if (preg_match('#'.preg_quote($name, '#').'(?![A-Za-z0-9])#', $names) !== 0) {
                return 'validation.pdf_active_content';
            }
        }

        foreach (self::VALUED as $name => $value) {
            if (preg_match('#'.preg_quote($name, '#').'(?:[\x00\t\n\f\r ]|%[^\r\n]*[\r\n])*+(?:'.$value.')#', $names) !== 0) {
                return 'validation.pdf_active_content';
            }
        }

        return null;
    }

    /**
     * محتوى التدفقات بعد فكّها، تدفقاً تدفقاً، حتى السقف. وما لا يُفك (صور مثلاً) يُتجاوز. وعدّ صفحات الكتاب يقرأ
     * منه كائنات تدفقات الكائنات (App\Support\LibraryPageCount).
     */
    public static function inflatedStreams(string $contents): string
    {
        $inflated = '';
        $offset = 0;

        while (($start = strpos($contents, 'stream', $offset)) !== false) {
            $data = $start + strlen('stream');

            if (substr($contents, $data, 2) === "\r\n") {
                $data += 2;
            } elseif (($contents[$data] ?? '') === "\n") {
                $data++;
            } else {
                $offset = $data;

                continue;
            }

            $end = strpos($contents, 'endstream', $data);

            if ($end === false) {
                break;
            }

            $remaining = self::MAX_INFLATED_BYTES - strlen($inflated);

            if ($remaining <= 0) {
                break;
            }

            $decoded = @zlib_decode(substr($contents, $data, $end - $data), $remaining);

            if (is_string($decoded)) {
                $inflated .= "\n".$decoded;
            }

            $offset = $end + strlen('endstream');
        }

        return $inflated;
    }
}
