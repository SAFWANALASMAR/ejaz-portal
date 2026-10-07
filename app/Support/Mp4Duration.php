<?php

namespace App\Support;

/**
 * مدة مقطع MP4 بالثواني من ترويسة mvhd داخل moov، بلا أداة خارجية. يتنقل بين الصناديق بأحجامها
 * (ومنها الحجم بـ 64 بت للصناديق الكبيرة)، فلا يقرأ محتوى المقطع نفسه، ويجد moov في أوله أو آخره.
 *
 * ولا يقبل إلا مدة موجبة من ملف غير مجزأ: الملف المجزأ (mvex أو moof) يكتب في mvhd صفراً ومدته
 * الحقيقية في أجزائه، فيُرفض ليُعاد تصديره. وهو البديل حين لا تُضبط أداة ffprobe (VideoProbe).
 */
final class Mp4Duration
{
    /**
     * @param  resource  $stream
     */
    public static function seconds($stream): ?float
    {
        $moov = self::findBox($stream, 'moov', 0, self::length($stream));

        if ($moov === null) {
            return null;
        }

        if (self::findBox($stream, 'mvex', $moov[0], $moov[1]) !== null || self::findBox($stream, 'moof', 0, self::length($stream)) !== null) {
            return null;
        }

        $mvhd = self::findBox($stream, 'mvhd', $moov[0], $moov[1]);

        if ($mvhd === null || fseek($stream, $mvhd[0]) !== 0) {
            return null;
        }

        $header = (string) fread($stream, 4);

        if (strlen($header) !== 4) {
            return null;
        }

        $version = ord($header[0]);
        // الإصدار 1: وقتا الإنشاء والتعديل 8 بايت لكل منهما، والمدة 8 بايت. والإصدار 0: 4 لكل منها
        $body = (string) fread($stream, $version === 1 ? 28 : 16);

        if ($version === 1 && strlen($body) === 28) {
            $timescale = unpack('N', substr($body, 16, 4))[1] ?? 0;
            $duration = unpack('J', substr($body, 20, 8))[1] ?? 0;
        } elseif ($version === 0 && strlen($body) === 16) {
            $timescale = unpack('N', substr($body, 8, 4))[1] ?? 0;
            $duration = unpack('N', substr($body, 12, 4))[1] ?? 0;
        } else {
            return null;
        }

        return $timescale > 0 && $duration > 0 ? $duration / $timescale : null;
    }

    /**
     * أول صندوق بالنوع المطلوب بين موضعين: [بداية محتواه، نهايته]، أو null.
     *
     * @param  resource  $stream
     * @return array{0: int, 1: int}|null
     */
    private static function findBox($stream, string $type, int $start, int $end): ?array
    {
        $position = $start;

        while ($position + 8 <= $end) {
            if (fseek($stream, $position) !== 0) {
                return null;
            }

            $header = (string) fread($stream, 8);

            if (strlen($header) !== 8) {
                return null;
            }

            $size = unpack('N', substr($header, 0, 4))[1] ?? 0;
            $boxType = substr($header, 4, 4);
            $headerLength = 8;

            if ($size === 1) {
                $large = (string) fread($stream, 8);

                if (strlen($large) !== 8) {
                    return null;
                }

                $size = unpack('J', $large)[1] ?? 0;
                $headerLength = 16;
            } elseif ($size === 0) {
                $size = $end - $position;
            }

            if ($size < $headerLength || $position + $size > $end) {
                return null;
            }

            if ($boxType === $type) {
                return [$position + $headerLength, $position + $size];
            }

            $position += $size;
        }

        return null;
    }

    /**
     * @param  resource  $stream
     */
    private static function length($stream): int
    {
        $stat = fstat($stream);

        return is_array($stat) ? (int) $stat['size'] : 0;
    }
}
