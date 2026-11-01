<?php

namespace App\Support;

/**
 * مجموعة صفحات مضغوطة في نص من نطاقات مرتبة بلا تكرار: «1-12,15,20-30». القراءة المتتابعة نطاق واحد مهما طال الكتاب،
 * فلا يكبر الصف بعدد الصفحات (library_progress.pages_seen — docs/decisions/2026-11-01-my-reading.md).
 */
final class PageSet
{
    /**
     * أعلى رقم صفحة يُقبل، فلا يُفك نطاق مضخَّم إلى ملايين الأرقام.
     */
    public const MAX_PAGE = 10_000;

    /**
     * @return list<int> الصفحات مرتبة بلا تكرار. وما لا يُفهم من النص يُتجاوز
     */
    public static function decode(?string $encoded): array
    {
        $pages = [];

        foreach (explode(',', (string) $encoded) as $part) {
            if (preg_match('/^(\d{1,6})(?:-(\d{1,6}))?$/', trim($part), $match) !== 1) {
                continue;
            }

            $from = (int) $match[1];
            $to = min(isset($match[2]) ? (int) $match[2] : $from, self::MAX_PAGE);

            if ($from >= 1 && $to >= $from) {
                array_push($pages, ...range($from, $to));
            }
        }

        $pages = array_values(array_unique($pages));
        sort($pages);

        return $pages;
    }

    /**
     * الصفحات من 1 إلى عدد صفحات الكتاب وحدها، فلا تُحسب صفحة فوق العدد الحقيقي (مراجعة المشرف على #117، م٢).
     *
     * @param  list<int>  $pages
     * @return list<int>
     */
    public static function clip(array $pages, int $total): array
    {
        return array_values(array_filter($pages, fn (int $page): bool => $page >= 1 && $page <= $total));
    }

    /**
     * @param  iterable<int>  $pages
     */
    public static function encode(iterable $pages): ?string
    {
        $sorted = array_values(array_unique(array_filter([...$pages], fn (int $page): bool => $page >= 1 && $page <= self::MAX_PAGE)));
        sort($sorted);

        if ($sorted === []) {
            return null;
        }

        $ranges = [];
        $start = $end = $sorted[0];

        foreach (array_slice($sorted, 1) as $page) {
            if ($page === $end + 1) {
                $end = $page;

                continue;
            }

            $ranges[] = $start === $end ? (string) $start : $start.'-'.$end;
            $start = $end = $page;
        }

        $ranges[] = $start === $end ? (string) $start : $start.'-'.$end;

        return implode(',', $ranges);
    }
}
