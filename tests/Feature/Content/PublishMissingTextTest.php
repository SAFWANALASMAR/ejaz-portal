<?php

use App\Enums\RoleName;
use App\Filament\Resources\NewsArticles\Pages\CreateNewsArticle;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\Program;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
| لا يُنشر عنوان أو ملخص فيه علامة النص الناقص «【» (مراجعة محركات البحث، 3 نوفمبر 2026)، والمسودة تُحفظ بها. والفحص في
| Publishable عند الحفظ، فيشمل نماذج اللوحة وكل طريق آخر.
*/

/**
 * @return array<string, mixed>
 */
function missingTextArticle(array $overrides = []): array
{
    return [
        'title' => 'الملتقى السنوي 【ناقص: العنوان المعتمد】',
        'slug' => 'annual-forum',
        'published_on' => '2026-02-01',
        'summary' => 'ملخص الخبر',
        'blocks' => [['type' => 'text', 'data' => ['heading' => 'تفاصيل', 'body' => '<p>نص الخبر</p>']]],
        'is_published' => true,
        ...$overrides,
    ];
}

beforeEach(function () {
    Storage::fake('public');
});

it('refuses to publish from the panel a title or a summary with the missing-text mark, and keeps saving it as a draft', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(CreateNewsArticle::class)->fillForm(missingTextArticle())->call('create')
        ->assertHasFormErrors(['title'])
        ->assertSee(__('content.missing_text_blocks_publish'));
    Livewire::test(CreateNewsArticle::class)->fillForm(missingTextArticle(['title' => 'الملتقى السنوي', 'summary' => '【ناقص: الملخص】']))->call('create')
        ->assertHasFormErrors(['summary']);
    expect(NewsArticle::query()->count())->toBe(0);

    Livewire::test(CreateNewsArticle::class)->fillForm(missingTextArticle(['is_published' => false]))->call('create')->assertHasNoFormErrors();

    expect(NewsArticle::query()->sole())->is_published->toBeFalse()->title->toContain('【');
});

it('blocks publishing a draft with the mark through any save, and lets a completed text be published', function () {
    $page = Page::factory()->unpublished()->create(['slug' => 'vision', 'summary' => 'رؤية 【ناقص】']);

    expect(fn () => $page->update(['is_published' => true]))->toThrow(ValidationException::class)
        ->and($page->fresh()->is_published)->toBeFalse()
        ->and(fn () => Program::factory()->create(['title' => 'برنامج 【ناقص】']))->toThrow(ValidationException::class);

    $page->update(['summary' => 'رؤية الجمعية', 'is_published' => true]);
    expect($page->fresh()->is_published)->toBeTrue();
});

it('does not stop an unrelated save of a record published before the rule', function () {
    $article = NewsArticle::factory()->create(['slug' => 'old-news']);
    DB::table('news_articles')->where('id', $article->id)->update(['title' => 'خبر قديم 【ناقص】']);

    $article->refresh()->update(['category' => 'فعاليات']);

    expect($article->fresh()->category)->toBe('فعاليات');
    expect(fn () => $article->fresh()->update(['title' => 'خبر جديد 【ناقص】']))->toThrow(ValidationException::class);
});
