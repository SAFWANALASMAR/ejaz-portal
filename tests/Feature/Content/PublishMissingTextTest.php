<?php

use App\Enums\RoleName;
use App\Filament\Resources\NewsArticles\Pages\CreateNewsArticle;
use App\Models\Album;
use App\Models\Competition;
use App\Models\Document;
use App\Models\LibraryItem;
use App\Models\LiveStream;
use App\Models\NewsArticle;
use App\Models\Opening;
use App\Models\Page;
use App\Models\Program;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
| لا يُنشر نص فيه علامة النص الناقص «【» (مراجعة محركات البحث، 3 نوفمبر 2026)، والمسودة تُحفظ بها. والفحص في
| Publishable عند الحفظ، فيشمل نماذج اللوحة وكل طريق آخر. والحقول `$missingTextFields` لكل نموذج: المتن والوصف وقيم
| المقاطع بما في داخلها لا العنوان والملخص وحدهما (قرار المشرف على طلب الدمج 147).
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

/*
| نموذج نموذج: الحقل بالعلامة يُحفظ مسودة ولا يُنشر، ومفتاح الخطأ اسمه، والنموذج نفسه بلا العلامة يُنشر. وقيم JSON تُفحص بما
| في داخلها، فالعلامة محفوظة فيها بترميز يونيكود (U+3010) لا حرفاً.
*/

dataset('missing text in a published field', [
    'news body inside blocks' => [NewsArticle::class, 'blocks', [['type' => 'text', 'data' => ['heading' => 'تفاصيل', 'body' => '<p>【ناقص: نص الخبر】</p>']]]],
    'page card text nested in blocks' => [Page::class, 'blocks', [['type' => 'cards', 'data' => ['heading' => 'القيم', 'items' => [['title' => 'قيمة', 'text' => '【ناقص: وصف القيمة】']]]]]],
    'album description' => [Album::class, 'description', 'وصف الألبوم 【ناقص】'],
    'competition terms' => [Competition::class, 'terms', '【ناقص: الشروط المعتمدة】'],
    'competition page content' => [Competition::class, 'page_content', ['intro' => ['heading' => 'المسابقة', 'body' => '【ناقص: التعريف】']]],
    'document description' => [Document::class, 'description', '【ناقص: وصف الوثيقة】'],
    'library item description' => [LibraryItem::class, 'description', 'وصف 【ناقص】'],
    'library item tag' => [LibraryItem::class, 'tags', ['علوم', '【ناقص】']],
    'live stream description' => [LiveStream::class, 'description', '【ناقص: وصف البث】'],
    'opening description' => [Opening::class, 'description', '【ناقص: وصف الفرصة】'],
    'opening form field label' => [Opening::class, 'form_fields', [['key' => 'about', 'label' => '【ناقص: السؤال】', 'type' => 'textarea', 'required' => true]]],
    'program description' => [Program::class, 'description', '【ناقص: وصف البرنامج】'],
    'program conditions' => [Program::class, 'conditions', 'الشروط: 【ناقص】'],
]);

it('refuses to publish a record with the missing-text mark in its body, description or nested values, and keeps it as a draft', function (string $class, string $field, mixed $value) {
    /** @var class-string<Model> $class */
    $draft = $class::factory()->create(['is_published' => false, $field => $value]);

    try {
        $draft->update(['is_published' => true]);
        $this->fail('published with the missing-text mark in '.$field);
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe([$field]);
    }

    expect($draft->fresh()->is_published)->toBeFalse()
        ->and(fn () => $class::factory()->create(['is_published' => true, $field => $value]))->toThrow(ValidationException::class);

    // والنموذج نفسه بلا العلامة يُنشر
    $clean = $class::factory()->create(['is_published' => false]);
    $clean->update(['is_published' => true]);
    expect($clean->fresh()->is_published)->toBeTrue();
})->with('missing text in a published field');

it('lists the checked fields on every published model, the body and nested values included', function () {
    $fields = fn (string $class): array => (new $class)->missingTextFields();

    expect($fields(NewsArticle::class))->toContain('title', 'summary', 'blocks')
        ->and($fields(Page::class))->toContain('title', 'summary', 'blocks')
        ->and($fields(Album::class))->toContain('title', 'description')
        ->and($fields(Competition::class))->toContain('title', 'description', 'terms', 'prizes', 'page_content', 'prize_table')
        ->and($fields(Document::class))->toContain('title', 'description')
        ->and($fields(LibraryItem::class))->toContain('title', 'description')
        ->and($fields(LiveStream::class))->toContain('title', 'description')
        ->and($fields(Opening::class))->toContain('title', 'summary', 'description')
        ->and($fields(Program::class))->toContain('title', 'summary', 'description', 'conditions');
});
