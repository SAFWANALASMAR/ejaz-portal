<?php

namespace App\Support;

use App\Contracts\ProgramStatsSource;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * «إحصائيات البرامج لكل نوع» (البند R23 — docs/decisions/2026-11-02-program-stats-by-type.md): صفوف مصادر
 * config('program-stats.sources') بترتيبها، ومعها نسبة الإكمال. تُحسب في الخلفية مع ejaz:refresh-stats كل عشر دقائق وتُخزَّن
 * كإحصائيات البوابة، فلوحة الإحصائيات وبوابة المجلس تقرآن المخزّن وحده بلا استعلام تجميعي عند الطلب.
 */
final class ProgramStatsByType
{
    private const KEY = 'portal.program-stats';

    /**
     * @return list<array{key: string, label: string, registered: int, completed: int, rate: int, pages: int|null, minutes: int|null}>
     */
    public static function compute(): array
    {
        $rows = [];

        foreach ((array) config('program-stats.sources', []) as $id) {
            $source = app($id);

            if (! $source instanceof ProgramStatsSource) {
                throw new InvalidArgumentException("Program stats source [{$id}] must implement ".ProgramStatsSource::class.'.');
            }

            // مصدر لاحق بمفتاح صف سابق يحل محله في موضعه
            foreach ($source->rows() as $row) {
                $rows[$row['key']] = self::normalize($row);
            }
        }

        return array_values($rows);
    }

    /**
     * يحسب ويخزّن بلا مدة: يبقى آخر حساب حتى يحل محله التالي (سياسة إخلاء Redis في performance.md).
     */
    public static function refresh(): void
    {
        Cache::forever(self::KEY, ['rows' => self::compute(), 'at' => now()->toIso8601String()]);
    }

    /**
     * @return list<array{key: string, label: string, registered: int, completed: int, rate: int, pages: int|null, minutes: int|null}>|null
     */
    public static function cached(): ?array
    {
        $stored = Cache::get(self::KEY);

        if (! is_array($stored) || ! is_array($stored['rows'] ?? null)) {
            return null;
        }

        /** @var list<array{key: string, label: string, registered: int, completed: int, rate: int, pages: int|null, minutes: int|null}> */
        return $stored['rows'];
    }

    /**
     * المخزّن لمن له stats.view وحده (لوحة الإحصائيات وبوابة المجلس)، وnull لغيره أو قبل أول حساب أو بلا صف.
     *
     * @return list<array{key: string, label: string, registered: int, completed: int, rate: int, pages: int|null, minutes: int|null}>|null
     */
    public static function visibleTo(?User $user): ?array
    {
        if (! ($user?->can('stats.view') ?? false)) {
            return null;
        }

        $rows = self::cached();

        return $rows === [] ? null : $rows;
    }

    /**
     * نسبة الإكمال مئوية صحيحة، وصفر بلا مسجل، ولا تتجاوز مئة.
     */
    public static function rate(int $registered, int $completed): int
    {
        return $registered > 0 ? min(100, (int) round($completed * 100 / $registered)) : 0;
    }

    /**
     * @param  array{key: string, label: string, registered: int, completed: int, pages: int|null, minutes: int|null}  $row
     * @return array{key: string, label: string, registered: int, completed: int, rate: int, pages: int|null, minutes: int|null}
     */
    private static function normalize(array $row): array
    {
        $registered = max(0, (int) $row['registered']);
        $completed = max(0, (int) $row['completed']);

        return [
            'key' => (string) $row['key'],
            'label' => (string) $row['label'],
            'registered' => $registered,
            'completed' => $completed,
            'rate' => self::rate($registered, $completed),
            'pages' => $row['pages'] === null ? null : max(0, (int) $row['pages']),
            'minutes' => $row['minutes'] === null ? null : max(0, (int) $row['minutes']),
        ];
    }
}
