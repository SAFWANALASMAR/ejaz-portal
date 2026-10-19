<?php

use App\Enums\AssistantReviewStatus;
use App\Models\AssistantChunk;
use App\Models\AssistantGuide;
use App\Support\Assistant\GuideRoutes;
use Database\Seeders\AssistantGuideSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
| «دليل استخدام البوابة»: المسودات مكتوبة من الشيفرة، فكل خطوة تطابق الموقع: كل صفحة مسار قائم بلا معاملات،
| وكل اسم زر أو صفحة بين «» نص من ملفات الترجمة مستعمل في القوالب أو الشيفرة.
*/

/**
 * @return list<array{slug: string, title: string, keywords: string, route: string, steps: list<string>, labels: list<string>}>
 */
function guideArticles(): array
{
    return require database_path(AssistantGuideSeeder::DATA);
}

/**
 * نصوص القوالب والشيفرة التي يُبحث فيها عن مفاتيح الترجمة.
 */
function uiSources(): string
{
    static $sources = null;

    return $sources ??= collect([...File::allFiles(resource_path('views')), ...File::allFiles(app_path()), ...File::allFiles(config_path())])
        ->map(fn ($file): string => $file->getContents())
        ->implode("\n");
}

it('has unique slugs and complete articles', function () {
    $articles = guideArticles();

    expect($articles)->not->toBeEmpty()
        ->and(collect($articles)->pluck('slug')->duplicates())->toBeEmpty();

    foreach ($articles as $article) {
        expect($article['slug'])->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
            ->and($article['title'])->not->toBeEmpty()
            ->and(count($article['steps']))->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(12);
    }
});

it('links every article to an existing public page without parameters', function () {
    foreach (guideArticles() as $article) {
        expect(Route::has($article['route']))->toBeTrue($article['slug'].': '.$article['route'])
            ->and(GuideRoutes::allows($article['route']))->toBeTrue($article['slug'].': '.$article['route'])
            ->and(route($article['route'], [], false))->toStartWith('/');
    }
});

it('quotes only interface texts that exist in the translations and in the templates', function () {
    foreach (guideArticles() as $article) {
        $steps = implode("\n", $article['steps']);

        foreach ($article['labels'] as $key) {
            $text = __($key);

            expect($text)->not->toBe($key, "{$article['slug']}: missing translation {$key}")
                ->and(is_string($text))->toBeTrue()
                ->and(str_contains($steps, '«'.$text.'»'))->toBeTrue("{$article['slug']}: «{$text}» is not quoted in the steps")
                ->and(str_contains(uiSources(), "'{$key}'"))->toBeTrue("{$article['slug']}: {$key} is not used in the views or the code");
        }

        // وكل نص بين «» اسم من الترجمات المذكورة، فلا اسم زر مخترع
        preg_match_all('/«([^»]+)»/u', $steps, $matches);
        $labels = array_map(fn (string $key): string => (string) __($key), $article['labels']);

        foreach (array_unique($matches[1]) as $quoted) {
            expect(in_array($quoted, $labels, true))->toBeTrue("{$article['slug']}: «{$quoted}» has no label key");
        }
    }
});

it('contains no invented contact details, dates or amounts', function () {
    foreach (guideArticles() as $article) {
        $text = implode(' ', [$article['title'], ...$article['steps']]);

        // «رابط يبدأ بـ https://» وصف لما يقبله الموقع، لا رابط
        expect($text)->not->toMatch('#@|https?://\w|\b9665\d|\b05\d{8}\b|\b20\d\d\b|ريال|ساعة العمل#u', $article['slug']);
    }
});

it('seeds the articles as drafts without touching edited or activated ones', function () {
    $this->seed(AssistantGuideSeeder::class);

    expect(AssistantGuide::query()->count())->toBe(count(guideArticles()))
        ->and(AssistantGuide::query()->where('status', '!=', AssistantReviewStatus::Draft->value)->exists())->toBeFalse()
        ->and(AssistantChunk::query()->where('source_type', 'guide')->exists())->toBeFalse();

    $first = AssistantGuide::query()->orderBy('sort')->first();
    $first->update(['title' => 'عنوان عدّله المسؤول', 'status' => AssistantReviewStatus::Reviewed]);

    $this->seed(AssistantGuideSeeder::class);

    expect($first->fresh()->title)->toBe('عنوان عدّله المسؤول')
        ->and(AssistantGuide::query()->count())->toBe(count(guideArticles()))
        ->and(AssistantChunk::query()->where('source_type', 'guide')->count())->toBe(1);
});
