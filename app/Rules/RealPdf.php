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
    private const ACTIVE = ['/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/RichMedia', '/XFA', '/SubmitForm', '/ImportData'];

    /**
     * سقف ما يُفك من التدفقات، فلا يُستنزف الخادم بملف مضغوط مضخَّم.
     */
    private const MAX_INFLATED_BYTES = 50 * 1024 * 1024;

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
        $names = (string) preg_replace_callback(
            '/#([0-9A-Fa-f]{2})/',
            fn (array $match): string => chr((int) hexdec($match[1])),
            $contents."\n".self::inflatedStreams($contents),
        );

        foreach (self::ACTIVE as $name) {
            if (preg_match('#'.preg_quote($name, '#').'(?![A-Za-z0-9])#', $names) === 1) {
                return 'validation.pdf_active_content';
            }
        }

        return null;
    }

    /**
     * محتوى التدفقات بعد فكّها، تدفقاً تدفقاً، حتى السقف. وما لا يُفك (صور مثلاً) يُتجاوز.
     */
    private static function inflatedStreams(string $contents): string
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
