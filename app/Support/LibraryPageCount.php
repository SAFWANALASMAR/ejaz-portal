<?php

namespace App\Support;

use App\Jobs\CountLibraryPages;
use App\Models\LibraryItem;
use App\Rules\RealPdf;

/**
 * عدد صفحات كتاب المكتبة من ملفه في الخادم، لا من المتصفح: به يُرفض رقم صفحة أكبر منه، وبه يُحسب الإتمام
 * (docs/decisions/2026-11-01-my-reading.md). بلا مكتبة جديدة: أكبر /Count في عقدة /Type /Pages (الجذر أكبرها)، في النص الخام
 * وفي تدفقات الكائنات بعد فكّها (RealPdf::inflatedStreams). وإلا عدد كائنات /Type /Page.
 * يحسبه CountLibraryPages في الطابور ويحفظه في library_items.page_count، فلا يُقرأ الملف على مسار البلاغ.
 */
final class LibraryPageCount
{
    /**
     * أبعد مسافة يُبحث فيها عن حدود القاموس حول /Type /Pages، فلا يُمسح ملف كامل لكل تطابق.
     */
    private const SCAN = 100_000;

    /**
     * أكثر عقد /Type /Pages تُفحص قبل الرجوع إلى عدّ كائنات /Type /Page.
     */
    public const MAX_PAGES_NODES = 1_000;

    /**
     * العدد المحفوظ، أو null حتى يُحسب. والكتاب الذي لم يُحاول عدّه قط (ومنه ما سبق هذا الفرع) يرسل مهمة العد مرة. أما ما تعذّر
     * عدّه فلا تُعاد مهمته مع كل بلاغ، بل عند تغيّر ملفه في SaveLibraryItem (مراجعة المشرف على #117، م١).
     */
    public static function for(LibraryItem $item): ?int
    {
        if (! $item->section->takesPdf() || blank($item->file)) {
            return null;
        }

        if ($item->page_count !== null && $item->page_count > 0) {
            return $item->page_count;
        }

        if ($item->page_count_checked_at === null) {
            CountLibraryPages::dispatch($item->id, (string) $item->file);
        }

        return null;
    }

    public static function count(string $contents): ?int
    {
        if (! str_starts_with($contents, '%PDF-')) {
            return null;
        }

        $text = $contents."\n".RealPdf::inflatedStreams($contents);
        $best = 0;
        $offset = 0;
        $examined = 0;

        while (preg_match('#/Type\s*/Pages(?![A-Za-z0-9])#', $text, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            // ملف بعقد /Pages فوق السقف لا يُمسح قاموساً قاموساً: يُعدّ بكائنات الصفحات وحدها (مراجعة المشرف على #117، ن١)
            if (++$examined > self::MAX_PAGES_NODES) {
                $best = 0;

                break;
            }

            $position = $match[0][1];
            $offset = $position + strlen($match[0][0]);
            $dictionary = self::topLevelOf($text, $position);

            if ($dictionary !== null && preg_match('#/Count\s+(\d{1,6})(?!\d)#', $dictionary, $count) === 1) {
                $best = max($best, (int) $count[1]);
            }
        }

        if ($best === 0) {
            $best = (int) preg_match_all('#/Type\s*/Page(?![A-Za-z0-9])#', $text);
        }

        return $best > 0 ? min($best, PageSet::MAX_PAGE) : null;
    }

    /**
     * نص القاموس الذي يحوي الموضع، بمستواه الأعلى وحده (بلا القواميس المتداخلة فيه)، فلا يُقرأ /Count من قاموس داخلي.
     */
    private static function topLevelOf(string $text, int $position): ?string
    {
        $start = null;
        $depth = 0;

        for ($i = $position - 1; $i > 0 && $position - $i < self::SCAN; $i--) {
            $pair = $text[$i - 1].$text[$i];

            if ($pair === '>>') {
                $depth++;
                $i--;
            } elseif ($pair === '<<') {
                if ($depth === 0) {
                    $start = $i + 1;

                    break;
                }

                $depth--;
                $i--;
            }
        }

        if ($start === null) {
            return null;
        }

        $top = '';
        $depth = 0;
        $length = strlen($text);

        for ($i = $start; $i < $length - 1 && $i - $start < self::SCAN; $i++) {
            $pair = $text[$i].$text[$i + 1];

            if ($pair === '<<') {
                $depth++;
                $i++;
            } elseif ($pair === '>>') {
                if ($depth === 0) {
                    return $top;
                }

                $depth--;
                $i++;
            } elseif ($depth === 0) {
                $top .= $text[$i];
            }
        }

        return null;
    }
}
