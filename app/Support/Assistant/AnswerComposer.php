<?php

namespace App\Support\Assistant;

use App\Models\AssistantChunk;
use App\Support\SpaLink;
use Illuminate\Support\Collection;

/**
 * يحوّل رد المزود إلى جواب للزائر. المصادر تُبنى من المقاطع المسترجعة في الخادم لا من نص النموذج: أرقام المقاطع
 * التي ذكرها الجواب ([1]) تختار منها، وإن لم يذكر رقماً فالمقاطع كلها مصادر. والرابط في نص النموذج لا يصير رابطاً
 * (الواجهة تعرضه نصاً).
 */
final class AnswerComposer
{
    public const NO_ANSWER = 'NO_ANSWER';

    private const MAX_ANSWER = 2500;

    private const MAX_SOURCES = 4;

    public static function isNoAnswer(string $text): bool
    {
        return str_contains($text, self::NO_ANSWER) || trim($text) === '';
    }

    public static function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // تنسيق Markdown الشائع يُحذف، فالجواب نص عادي
        $text = (string) preg_replace('/^\s{0,3}#{1,6}\s*/mu', '', $text);
        $text = str_replace(['**', '__', '`'], '', $text);

        return mb_substr(trim($text), 0, self::MAX_ANSWER);
    }

    /**
     * @param  Collection<int, AssistantChunk>  $chunks
     * @return list<array{title: string, url: string, navigate: bool}>
     */
    public static function sources(string $text, Collection $chunks): array
    {
        $chunks = $chunks->values();
        preg_match_all('/\[(\d{1,2})\]/u', strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']), $matches);

        $cited = collect($matches[1])
            ->map(fn (string $number): ?AssistantChunk => $chunks->get((int) $number - 1))
            ->filter();

        return self::links($cited->isNotEmpty() ? $cited : $chunks);
    }

    /**
     * @param  Collection<int, AssistantChunk>  $chunks
     * @return list<array{title: string, url: string, navigate: bool}>
     */
    public static function links(Collection $chunks): array
    {
        return $chunks
            ->unique('url')
            ->take(self::MAX_SOURCES)
            ->map(fn (AssistantChunk $chunk): array => ['title' => $chunk->title, 'url' => $chunk->url, 'navigate' => SpaLink::allows($chunk->url)])
            ->values()
            ->all();
    }
}
