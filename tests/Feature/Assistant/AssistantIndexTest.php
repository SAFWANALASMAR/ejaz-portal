<?php

use App\Enums\AssistantSource;
use App\Enums\LibrarySection;
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
use App\Support\Assistant\ArabicText;
use App\Support\Assistant\AssistantIndex;
use App\Support\Assistant\AssistantRetriever;
use Illuminate\Support\Facades\Artisan;

/*
| فهرس المساعد: المحتوى المنشور وحده بكل مصادره، ويتبع النشر والحذف والاستعادة، ولا يقرأ الملفات الخاصة.
| والتطبيع العربي في السؤال والمحتوى بالقواعد نفسها.
*/

it('normalizes Arabic spelling, diacritics and common prefixes', function () {
    expect(ArabicText::normalize('الإعجازُ العلميّ في القرآنِ والسُّنّة'))->toBe('اعجاز علمي في قران سنه')
        ->and(ArabicText::normalize('أسئلة ـ مسـابقة ٢٠٢٦'))->toBe('اسيله مسابقه 2026')
        ->and(ArabicText::normalize('ولد الف'))->toBe('ولد الف')
        ->and(ArabicText::terms('كيف أسجّل في البرامج؟ وين ألقى المكتبة'))->toBe(['اسجل', 'برامج', 'القي', 'مكتبه']);
});

it('matches questions written with different spellings', function () {
    Page::factory()->create(['slug' => 'research', 'title' => 'الأبحاث العلمية', 'summary' => 'مسابقة الإعجاز السنوية.']);

    foreach (['ابحاث', 'الأبحاث', 'مسابقة', 'مسابقه', 'الاعجاز', 'إعجاز'] as $question) {
        expect(AssistantRetriever::search($question, 3))->toHaveCount(1, $question);
    }
});

it('indexes published content from every public source with its page link', function () {
    Page::factory()->create(['slug' => 'vision', 'title' => 'الرؤية', 'summary' => 'كلمةمميزة الصفحة', 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => 'عنوان', 'body' => '<p>فقرة <strong>كلمةمميزة</strong> المقاطع</p>']],
        ['type' => 'cards', 'data' => ['items' => [['title' => 'بطاقة', 'text' => 'كلمةمميزة البطاقة']]]],
    ]]);
    Initiative::factory()->create(['slug' => 'qiyam', 'name' => 'قيم', 'summary' => 'كلمةمميزة المبادرة', 'goals' => ['هدف كلمةمميزة']]);
    Document::factory()->create(['slug' => 'policy', 'title' => 'لائحة', 'description' => 'كلمةمميزة الوثيقة']);
    LibraryItem::factory()->create(['slug' => 'book', 'section' => LibrarySection::Books, 'title' => 'كتاب', 'description' => 'كلمةمميزة الكتاب']);
    NewsArticle::factory()->create(['slug' => 'news-1', 'title' => 'خبر', 'summary' => 'كلمةمميزة الخبر']);
    Program::factory()->create(['slug' => 'course', 'title' => 'دورة', 'summary' => 'كلمةمميزة البرنامج']);
    Competition::factory()->create(['slug' => 'contest', 'title' => 'مسابقة', 'description' => 'كلمةمميزة المسابقة']);
    Opening::factory()->create(['slug' => 'job', 'title' => 'وظيفة', 'summary' => 'كلمةمميزة الخدمة']);
    AssistantFaq::factory()->create(['question' => 'سؤال كلمةمميزة', 'source_url' => '/contact']);
    AssistantGuide::factory()->create(['title' => 'دليل كلمةمميزة', 'route_name' => 'programs.index']);

    $urls = AssistantChunk::query()->orderBy('url')->pluck('url', 'source_type')->all();

    expect(collect(AssistantSource::cases())->every(fn (AssistantSource $source): bool => array_key_exists($source->value, $urls)))->toBeTrue()
        ->and($urls)->toMatchArray([
            'page' => '/pages/vision',
            'initiative' => '/initiatives/qiyam',
            'document' => '/governance/policy',
            'library' => '/library/books/book',
            'news' => '/media/news/news-1',
            'program' => '/programs/course',
            'competition' => '/competitions/contest',
            'opening' => '/services/openings/job',
            'faq' => '/contact',
            'guide' => '/programs',
        ]);

    $page = AssistantChunk::query()->where('source_type', 'page')->sole();
    expect($page->body)->toContain('فقرة كلمةمميزة المقاطع')->toContain('كلمةمميزة البطاقة')->not->toContain('<');

    expect(AssistantRetriever::search('كلمةمميزة', 20)->count())->toBe(10);
});

it('keeps drafts out and follows publishing, deleting and restoring', function () {
    $page = Page::factory()->create(['title' => 'الرؤية', 'is_published' => false]);
    $faq = AssistantFaq::factory()->draft()->create();
    $guide = AssistantGuide::factory()->draft()->create();
    $opening = Opening::factory()->create(['title' => 'وظيفة']);

    expect(AssistantChunk::query()->whereIn('source_type', ['page', 'faq', 'guide'])->count())->toBe(0);

    $page->update(['is_published' => true]);
    $faq->update(['status' => 'approved']);
    $guide->update(['status' => 'reviewed']);
    expect(AssistantChunk::query()->whereIn('source_type', ['page', 'faq', 'guide'])->count())->toBe(3);

    $page->update(['is_published' => false]);
    $faq->update(['status' => 'draft']);
    $guide->delete();
    expect(AssistantChunk::query()->whereIn('source_type', ['page', 'faq', 'guide'])->count())->toBe(0);

    $opening->delete();
    expect(AssistantChunk::query()->where('source_type', 'opening')->exists())->toBeFalse();

    $opening->restore();
    expect(AssistantChunk::query()->where('source_type', 'opening')->exists())->toBeTrue();
});

it('indexes the title and description of governance and library items, never their private files', function () {
    $document = Document::factory()->create(['title' => 'اللائحة الأساسية', 'description' => 'وصف منشور', 'file' => Document::DIRECTORY.'/secret.pdf']);

    $chunk = AssistantChunk::query()->where('source_type', 'document')->sole();
    expect($chunk->title)->toBe('اللائحة الأساسية')
        ->and($chunk->body)->toContain('وصف منشور')
        ->and($chunk->body)->not->toContain('secret.pdf')
        ->and($chunk->url)->toBe('/governance/'.$document->slug);
});

it('splits long content into passages within the limit', function () {
    $paragraphs = collect(range(1, 12))->map(fn (int $i): string => str_repeat('كلمة'.$i.' ', 40))->implode("\n");
    $chunks = AssistantIndex::split($paragraphs);

    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk))->toBeLessThanOrEqual((int) config('assistant.chunk_chars'));
    }
});

it('rebuilds the whole index from the command', function () {
    Page::factory()->create(['title' => 'الرؤية']);
    AssistantChunk::query()->delete();

    Artisan::call('ejaz:assistant-index');

    expect(AssistantChunk::query()->where('source_type', 'page')->count())->toBe(1);
});
