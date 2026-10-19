<?php

namespace App\Support\Assistant;

use App\Enums\AssistantSource;
use App\Models\AssistantChunk;
use Illuminate\Support\Collection;

/**
 * يسترجع أقرب المقاطع لسؤال من فهرس المساعد بالبحث النصي في PostgreSQL (requirements/10): كلمات السؤال المطبَّعة
 * يكفي أحدها (OR) بمطابقة البادئة، والترتيب بـ ts_rank_cd والعنوان أثقل، والأسئلة المعتمدة والدليل مضاعفة،
 * ومقاطع الصفحة الحالية للزائر أعلى قليلاً. ولا يُعاد من السجل الواحد أكثر من مقطعين.
 */
final class AssistantRetriever
{
    private const PER_SOURCE = 2;

    /**
     * @param  list<AssistantSource>|null  $sources  null لكل المصادر
     * @return Collection<int, AssistantChunk>
     */
    public static function search(string $question, int $limit, ?array $sources = null, ?string $currentPath = null): Collection
    {
        $terms = ArabicText::terms($question);

        if ($terms === [] || $limit < 1) {
            return collect();
        }

        // الكلمات حروف وأرقام وحدها بعد التطبيع، فلا عامل من عوامل tsquery فيها
        $query = implode(' | ', array_map(fn (string $term): string => mb_strlen($term) >= 3 ? $term.':*' : $term, $terms));
        $curated = array_map(fn (AssistantSource $source): string => $source->value, array_filter(AssistantSource::cases(), fn (AssistantSource $source): bool => $source->isCurated()));

        $chunks = AssistantChunk::query()
            ->select(['id', 'source_type', 'source_id', 'position', 'title', 'url', 'body'])
            ->selectRaw(
                'ts_rank_cd(search, to_tsquery(\'simple\', ?)) * (case when source_type in ('.implode(',', array_fill(0, count($curated), '?')).') then 2 else 1 end)'
                .' + (case when url = ? then 0.05 else 0 end) as rank',
                [$query, ...$curated, (string) $currentPath],
            )
            ->whereRaw('search @@ to_tsquery(\'simple\', ?)', [$query])
            ->when($sources !== null, fn ($builder) => $builder->whereIn('source_type', array_map(fn (AssistantSource $source): string => $source->value, (array) $sources)))
            ->orderByDesc('rank')
            ->orderBy('id')
            ->limit($limit * 4)
            ->get();

        $seen = [];

        return $chunks
            ->filter(function (AssistantChunk $chunk) use (&$seen): bool {
                $key = $chunk->source_type->value.':'.$chunk->source_id;
                $seen[$key] = ($seen[$key] ?? 0) + 1;

                return $seen[$key] <= self::PER_SOURCE;
            })
            ->take($limit)
            ->values();
    }
}
