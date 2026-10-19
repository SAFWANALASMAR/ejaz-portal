<?php

namespace App\Support\Assistant;

use App\Enums\AssistantSource;
use App\Enums\DocumentCategory;
use App\Models\AssistantChunk;
use App\Models\AssistantFaq;
use App\Models\AssistantGuide;
use App\Models\Competition;
use App\Models\Document;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Opening;
use App\Models\Page;
use App\Models\Program;
use App\Support\ContentBlocks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * فهرس المساعد: مقاطع من المحتوى المنشور وحده، بعنوانه ورابطه في الموقع ونصه المطبَّع (requirements/10).
 * يُحدَّث مع حفظ كل سجل وحذفه واستعادته (AppServiceProvider)، ويُعاد بناؤه كاملاً بالأمر ejaz:assistant-index
 * بعد النشر أو الاستعادة. وما ليس منشوراً (مسودة، أو محذوف، أو سؤال غير معتمد) لا يبقى له مقطع، والملفات الخاصة
 * لا تُقرأ: من الوثيقة وعنصر المكتبة عنوانهما ووصفهما وحدهما.
 */
final class AssistantIndex
{
    /**
     * النماذج المفهرسة ومصدر كل منها.
     *
     * @var array<class-string<Model>, AssistantSource>
     */
    public const MODELS = [
        AssistantFaq::class => AssistantSource::Faq,
        AssistantGuide::class => AssistantSource::Guide,
        Page::class => AssistantSource::Page,
        Initiative::class => AssistantSource::Initiative,
        Document::class => AssistantSource::Document,
        LibraryItem::class => AssistantSource::Library,
        NewsArticle::class => AssistantSource::News,
        Program::class => AssistantSource::Program,
        Competition::class => AssistantSource::Competition,
        Opening::class => AssistantSource::Opening,
    ];

    /**
     * يعيد مقاطع سجل واحد بعد اعتماد المعاملة: يحذف القديمة، ويضيف الجديدة إن كان منشوراً.
     */
    public static function sync(Model $model): void
    {
        $source = self::MODELS[$model::class] ?? null;

        if ($source === null) {
            return;
        }

        DB::afterCommit(function () use ($model, $source): void {
            DB::transaction(function () use ($model, $source): void {
                AssistantChunk::query()->where('source_type', $source->value)->where('source_id', $model->getKey())->delete();
                self::insert($source, $model);
            });
        });
    }

    /**
     * يبني الفهرس كله من جديد، ويعيد عدد المقاطع.
     */
    public static function rebuild(): int
    {
        return DB::transaction(function (): int {
            AssistantChunk::query()->delete();

            foreach (self::MODELS as $class => $source) {
                $class::query()->orderBy('id')->chunk(200, function ($models) use ($source): void {
                    foreach ($models as $model) {
                        self::insert($source, $model);
                    }
                });
            }

            return AssistantChunk::query()->count();
        });
    }

    private static function insert(AssistantSource $source, Model $model): void
    {
        $document = self::document($source, $model);

        if ($document === null) {
            return;
        }

        [$title, $url, $text, $keywords] = $document;
        $now = now();
        $rows = [];

        foreach (self::split($text) as $position => $body) {
            $rows[] = [
                'source_type' => $source->value,
                'source_id' => $model->getKey(),
                'position' => $position,
                'title' => mb_substr($title, 0, 300),
                'url' => $url,
                'body' => $body,
                'title_norm' => ArabicText::normalize($title.' '.$keywords),
                'body_norm' => ArabicText::normalize($body),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        AssistantChunk::query()->insert($rows);
    }

    /**
     * العنوان والرابط النسبي والنص والكلمات المفتاحية لسجل منشور، أو null لما لا يُفهرس.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}|null
     */
    private static function document(AssistantSource $source, Model $model): ?array
    {
        // المحذوف نهائياً (exists = false بعد الحذف) أو حذفاً ناعماً لا يُفهرس
        if (! $model->exists || (method_exists($model, 'trashed') && $model->trashed())) {
            return null;
        }

        return match (true) {
            $model instanceof AssistantFaq => $model->isActive()
                ? [$model->question, $model->source_url, $model->answer, (string) $model->keywords]
                : null,
            $model instanceof AssistantGuide => $model->isActive() && $model->path() !== null
                ? [$model->title, (string) $model->path(), self::steps($model->stepList()), (string) $model->keywords]
                : null,
            $model instanceof Page => $model->is_published
                ? [(string) $model->title, self::pageUrl($model->slug), self::join([$model->summary, self::blocks($model->blocks)]), '']
                : null,
            $model instanceof Initiative => $model->is_published
                ? [(string) $model->name, route('initiatives.show', ['slug' => $model->slug], false), self::join([$model->summary, self::join((array) $model->goals), self::blocks($model->blocks)]), '']
                : null,
            $model instanceof Document => $model->is_published
                ? [(string) $model->title, route('governance.show', ['slug' => $model->slug], false), self::join([DocumentCategory::tryFrom((string) $model->getRawOriginal('category'))?->getLabel(), $model->description]), '']
                : null,
            $model instanceof LibraryItem => $model->is_published
                ? [(string) $model->title, route('library.show', ['section' => $model->section->value, 'slug' => $model->slug], false), self::join([$model->section->getLabel(), $model->description, $model->author, self::join($model->tagList())]), '']
                : null,
            $model instanceof NewsArticle => $model->is_published
                ? [(string) $model->title, route('media.news.show', ['slug' => $model->slug], false), self::join([$model->summary, self::blocks($model->blocks)]), '']
                : null,
            $model instanceof Program => $model->is_published
                ? [(string) $model->title, route('programs.show', ['slug' => $model->slug], false), self::join([$model->summary, $model->description, $model->audience, $model->conditions]), '']
                : null,
            $model instanceof Competition => $model->is_published
                ? [(string) $model->title, route('competitions.show', ['slug' => $model->slug], false), self::join([$model->description, $model->terms, $model->prizes]), '']
                : null,
            $model instanceof Opening => $model->is_published
                ? [(string) $model->title, route('services.openings.show', ['slug' => $model->slug], false), self::join([$model->kind->getLabel(), $model->summary, $model->description]), '']
                : null,
            default => null,
        };
    }

    /**
     * صفحة «عن الجمعية» و«الخصوصية» برابطيهما، وصفحة المقدمة بقالبها (Page::INTROS)، وغيرها /pages/{slug}.
     */
    private static function pageUrl(string $slug): string
    {
        return match ($slug) {
            Page::ABOUT => route('about', [], false),
            Page::PRIVACY => route('privacy', [], false),
            default => Page::introUrl($slug, false) ?? route('pages.show', ['slug' => $slug], false),
        };
    }

    /**
     * @param  list<string>  $steps
     */
    private static function steps(array $steps): string
    {
        return implode("\n", array_map(fn (string $step, int $index): string => ($index + 1).'. '.$step, $steps, array_keys($steps)));
    }

    private static function blocks(mixed $blocks): string
    {
        $parts = [];

        foreach (ContentBlocks::normalize($blocks) as $block) {
            $parts[] = $block['data']['heading'] ?? null;
            $parts[] = $block['type'] === 'text' ? ($block['data']['body'] ?? null) : null;

            foreach ($block['type'] === 'cards' ? (array) ($block['data']['items'] ?? []) : [] as $item) {
                $parts[] = is_array($item) ? self::join([$item['title'] ?? null, $item['text'] ?? null]) : null;
            }
        }

        return self::join($parts);
    }

    /**
     * نص عادي من قيم قد تكون HTML أو مصفوفات: الوسوم تُحذف والكتل تفصلها أسطر.
     *
     * @param  array<mixed>  $parts
     */
    private static function join(array $parts): string
    {
        $lines = [];

        foreach ($parts as $part) {
            $text = is_array($part) ? self::join($part) : (is_scalar($part) ? (string) $part : '');
            // نهايات الكتل في HTML أسطر قبل حذف الوسوم، فلا تلتصق الفقرات
            $text = (string) preg_replace('#<(br|/p|/h[1-6]|/li|/blockquote|/div)\b[^>]*>#i', "\n", $text);
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim((string) preg_replace("/[ \t]+/u", ' ', $text));

            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * يقسم النص مقاطع بحد أقصى من الأحرف عند حدود الأسطر، والسطر الأطول من الحد يُقطع عند مسافة.
     *
     * @return list<string>
     */
    public static function split(string $text): array
    {
        $limit = (int) config('assistant.chunk_chars');
        $chunks = [];
        $current = '';

        foreach (preg_split("/\n+/u", trim($text)) ?: [] as $line) {
            while (mb_strlen($line) > $limit) {
                $cut = mb_strrpos(mb_substr($line, 0, $limit), ' ') ?: $limit;
                $chunks[] = trim(($current !== '' ? $current."\n" : '').mb_substr($line, 0, $cut));
                $current = '';
                $line = trim(mb_substr($line, $cut));
            }

            if ($current !== '' && mb_strlen($current) + mb_strlen($line) + 1 > $limit) {
                $chunks[] = $current;
                $current = '';
            }

            $current = $current === '' ? $line : $current."\n".$line;
        }

        if (trim($current) !== '' || $chunks === []) {
            $chunks[] = trim($current);
        }

        return array_values(array_filter($chunks, fn (string $chunk): bool => $chunk !== '')) ?: [''];
    }
}
