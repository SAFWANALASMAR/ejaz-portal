<?php

namespace App\Support;

use App\Models\Competition;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * الرابط المختصر الثابت لمسابقة الطلاب (/c/{code}) يُعمَّم على المدارس عبر إدارة النشاط الطلابي، ورمز QR له
 * (مطوية «البراهين العلمية»). والرمز من مكتبة bacon/bacon-qr-code القائمة مع Fortify، بلا مكتبة جديدة.
 */
final class CompetitionShortLink
{
    /**
     * حروف الرمز: صغيرة بلا ما يلتبس (l و1، وo و0).
     */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public const LENGTH = 6;

    public static function assign(Competition $competition): void
    {
        for ($attempt = 1; ; $attempt++) {
            $code = '';

            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $competition->short_code = $code;

            try {
                DB::transaction(fn () => $competition->save());

                return;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 5) {
                    throw $exception;
                }
            }
        }
    }

    public static function url(Competition $competition): ?string
    {
        return $competition->short_code !== null ? route('competitions.short', $competition->short_code) : null;
    }

    /**
     * رمز QR للرابط بصيغة SVG (للعرض في اللوحة وللطباعة)، بلا سطر تعريف XML.
     */
    public static function qrSvg(string $url): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(320, 2), new SvgImageBackEnd)))->writeString($url);

        return trim(substr($svg, (int) strpos($svg, "\n") + 1));
    }
}
