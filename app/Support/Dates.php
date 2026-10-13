<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use IntlDateFormatter;

/**
 * عرض التواريخ للمستخدم بتوقيت الرياض وبصيغة «15 أكتوبر 2026»،
 * والتاريخ الهجري (أم القرى) حيث تطلبه الجمعية. التخزين يبقى بتوقيت UTC.
 */
final class Dates
{
    public static function local(DateTimeInterface $date): Carbon
    {
        return Carbon::instance($date)->setTimezone(config('app.display_timezone'));
    }

    public static function display(?DateTimeInterface $date, bool $withTime = false): string
    {
        if ($date === null) {
            return '';
        }

        $local = self::local($date);
        $text = $local->day.' '.__('dates.months.'.$local->month).' '.$local->year;

        if ($withTime) {
            $text .= ' — '.$local->format('g:i').' '.__($local->hour < 12 ? 'dates.am' : 'dates.pm');
        }

        return $text;
    }

    public static function hijri(?DateTimeInterface $date): string
    {
        if ($date === null) {
            return '';
        }

        $formatter = new IntlDateFormatter(
            'ar_SA@calendar=islamic-umalqura;numbers=latn',
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            config('app.display_timezone'),
            IntlDateFormatter::TRADITIONAL,
            'd MMMM y',
        );

        return (string) $formatter->format(self::local($date)).' '.__('dates.hijri_suffix');
    }
}
