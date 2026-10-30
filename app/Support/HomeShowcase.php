<?php

namespace App\Support;

use App\Models\Album;
use App\Models\Competition;
use App\Models\NewsArticle;
use App\Models\Page;

/**
 * بنية الرئيسية من مرجع المدير التنفيذي (قرار صفوان، 30 أكتوبر 2026 — plans/2026-10-30-director-home-reference.md) من
 * بيانات البوابة وحدها: صور العارض، وشرائح الهوية، ومجموع جوائز البراهين، والمستجدات. ولا نص ولا رقم ولا صورة من المرجع.
 */
final class HomeShowcase
{
    /**
     * صور العارض في البطل.
     */
    public const SLIDES = 5;

    /**
     * صور العارض: صورة البطل من «إعدادات الموقع» إن رُفعت، ثم صور أحدث الألبومات المنشورة (مكتبة صور الجمعية). وإن لم
     * يوجد شيء فالرسم المؤقت المحايد وحده (التصحيحات ٤ في ui-reference.md)، شريحة واحدة بلا عارض.
     *
     * @param  array<string, mixed>  $home
     * @return list<array{src: string, srcset: ?string, alt: string}>
     */
    public static function slides(array $home): array
    {
        $slides = [];
        $image = is_string($home['hero_image'] ?? null) && trim($home['hero_image']) !== '' ? trim($home['hero_image']) : null;

        if ($image !== null) {
            $slides[] = [...HeroImage::sources($image), 'alt' => is_string($home['hero_image_alt'] ?? null) ? trim($home['hero_image_alt']) : ''];
        }

        foreach (Album::query()->published()->newestFirst()->limit(self::SLIDES)->get() as $album) {
            // صورتان من كل ألبوم على الأكثر، فتتنوع الفعاليات
            foreach (array_slice($album->photoList(), 0, 2) as $photo) {
                if (count($slides) >= self::SLIDES) {
                    break 2;
                }

                $slides[] = [...HeroImage::sources($photo), 'alt' => $album->title];
            }
        }

        return $slides !== [] ? $slides : [['src' => asset('images/placeholders/architecture.svg'), 'srcset' => null, 'alt' => '']];
    }

    /**
     * شرائح الهوية بترتيبها: الرؤية، ثم الرسالة، ثم القيم واحدة واحدة بأيقوناتها. والفارغة لا شريحة لها. والرؤية والرسالة
     * من «محتوى الرئيسية»، وإلا من مقطعيهما في «عن الجمعية» المنشورة (نصها المعتمد).
     *
     * @param  array<string, mixed>  $content  HomeContent::resolved()
     * @return list<array{kind: string, title: string, text: string, icon: string}>
     */
    public static function identity(array $content): array
    {
        $slides = [];
        $about = null;

        foreach (['vision' => 'eye', 'mission' => 'paper-airplane'] as $key => $icon) {
            $text = trim((string) ($content['identity'][$key] ?? ''));

            if ($text === '') {
                $about ??= self::aboutSections();
                $text = $about[(string) __('ui.identity.'.$key)] ?? '';
            }

            if ($text !== '') {
                $slides[] = ['kind' => $key, 'title' => (string) __('ui.identity.'.$key), 'text' => $text, 'icon' => $icon];
            }
        }

        foreach ((array) ($content['values']['items'] ?? []) as $value) {
            $title = trim((string) ($value['title'] ?? ''));

            if ($title !== '') {
                $slides[] = ['kind' => 'value', 'title' => $title, 'text' => trim((string) ($value['text'] ?? '')), 'icon' => (string) ($value['icon'] ?? 'star')];
            }
        }

        return $slides;
    }

    /**
     * مقاطع النص في «عن الجمعية» المنشورة: العنوان ← النص بلا وسوم.
     *
     * @return array<string, string>
     */
    private static function aboutSections(): array
    {
        $page = Page::query()->published()->where('slug', Page::ABOUT)->first();
        $sections = [];

        foreach (ContentBlocks::normalize($page->blocks ?? []) as $block) {
            if ($block['type'] === 'text' && is_string($block['data']['heading'] ?? null)) {
                $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('</p>', "</p>\n", (string) ($block['data']['body'] ?? ''))))) ?? '');

                if ($text !== '') {
                    $sections[trim($block['data']['heading'])] = $text;
                }
            }
        }

        return $sections;
    }

    /**
     * مسابقة البراهين المنشورة (مسابقة الطلاب الأحدث) ومجموع جوائزها من جدولها، أو null بلا مسابقة أو بلا جوائز.
     *
     * @return array{title: string, total: int, url: string}|null
     */
    public static function prize(): ?array
    {
        $competition = Competition::query()->published()->where('student_mode', true)->latest('id')->first();

        if ($competition === null) {
            return null;
        }

        $total = self::prizeTotal($competition);

        return $total > 0 ? ['title' => $competition->title, 'total' => $total, 'url' => route('competitions.show', $competition->slug)] : null;
    }

    /**
     * مجموع جوائز المراكز للجنسين.
     */
    public static function prizeTotal(Competition $competition): int
    {
        return array_sum(array_map(fn (array $row): int => max(0, $row['male']) + max(0, $row['female']), $competition->prizeRows()));
    }

    /**
     * الخبر «جديد»: نُشر في الأيام السبعة الأخيرة.
     */
    public static function isNew(NewsArticle $article): bool
    {
        return $article->published_on->gte(now()->subDays(7)->startOfDay());
    }
}
