<?php

use App\Enums\DocumentCategory;
use App\Models\Document;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

/**
 * وثيقة بملفها في القرص الخاص.
 *
 * @param  array<string, mixed>  $attributes
 */
function storedDocument(array $attributes = []): Document
{
    $document = Document::factory()->create($attributes);
    Storage::disk('local')->put($document->file, pdfContents());

    return $document;
}

beforeEach(function () {
    showLegacyHomeSections();
});

it('lists the published documents under the category tiles, newest first', function () {
    storedDocument(['title' => 'اللائحة الأقدم', 'issued_at' => '2024-01-10']);
    storedDocument(['title' => 'التقرير الأحدث', 'issued_at' => '2026-03-01', 'category' => DocumentCategory::AnnualReports]);
    storedDocument(['title' => 'وثيقة غير منشورة', 'is_published' => false]);

    $this->get('/governance')->assertOk()
        ->assertSeeInOrder(['<h1', __('governance.title')], false)
        ->assertSee(__('governance.categories.financial-reports'))
        ->assertSeeInOrder(['التقرير الأحدث', 'اللائحة الأقدم'])
        ->assertDontSee('وثيقة غير منشورة');
});

it('lists the documents without an issue date after the dated ones', function () {
    storedDocument(['title' => 'وثيقة بلا تاريخ', 'issued_at' => null]);
    storedDocument(['title' => 'وثيقة مؤرخة', 'issued_at' => '2020-01-01']);

    $this->get('/governance')->assertSeeInOrder(['وثيقة مؤرخة', 'وثيقة بلا تاريخ']);
});

it('filters by category, year and title, taking % and _ as plain letters', function () {
    storedDocument(['title' => 'سياسة الخصوصية', 'issued_at' => '2025-05-01', 'category' => DocumentCategory::Policies]);
    storedDocument(['title' => 'التقرير المالي', 'issued_at' => '2026-02-01', 'category' => DocumentCategory::FinancialReports]);

    $this->get('/governance?category=financial-reports')->assertSee('التقرير المالي')->assertDontSee('سياسة الخصوصية');
    $this->get('/governance?year=2025')->assertSee('سياسة الخصوصية')->assertDontSee('التقرير المالي');
    $this->get('/governance?q=الخصوصية')->assertSee('سياسة الخصوصية')->assertDontSee('التقرير المالي');
    $this->get('/governance?q=%25')->assertSee(__('governance.list.no_results'));
    $this->get('/governance?q=_')->assertSee(__('governance.list.no_results'));
});

it('sends an invalid filter back to the full list', function (string $query) {
    $this->get('/governance?'.$query)->assertRedirect(route('governance.index'));
})->with(['category=evil', 'year=abc', 'year=1200', 'q='.str_repeat('x', 101), 'page=0', 'q=%FF']);

it('keeps only the validated filters in the page links of the cached list', function () {
    Document::factory()->count(13)->create(['category' => DocumentCategory::Policies]);

    $this->get('/governance?category=policies')->assertSee(route('governance.index', ['category' => 'policies', 'page' => 2]));
    expect($this->get('/governance?utm=evil')->assertOk()->getContent())->not->toContain('utm');
    expect($this->get('/governance')->getContent())->not->toContain('utm');
});

it('answers 404 for a page after the last one, with or without a filter', function () {
    storedDocument(['title' => 'الوثيقة الوحيدة']);

    $this->get('/governance?page=1')->assertOk()->assertSee('الوثيقة الوحيدة');
    $this->get('/governance?page=2')->assertNotFound();
    $this->get('/governance?q=الوحيدة&page=2')->assertNotFound();
    $this->get('/governance?q=لا-يوجد')->assertOk()->assertSee(__('governance.list.no_results'));
});

it('shows a published document with its file embedded and a download link', function () {
    $document = storedDocument(['slug' => 'privacy-policy', 'title' => 'سياسة الخصوصية', 'version' => '2', 'description' => 'وصف الوثيقة']);

    $this->get('/governance/privacy-policy')->assertOk()
        ->assertSeeInOrder(['<h1', 'سياسة الخصوصية'], false)
        ->assertSee('وصف الوثيقة')
        ->assertSee('<iframe src="'.route('governance.file', 'privacy-policy').'"', false)
        ->assertSee('href="'.route('governance.download', 'privacy-policy').'"', false);

    expect($document->fresh()->is_published)->toBeTrue();
});

it('serves the file for embedding on the site only, and as a download', function () {
    storedDocument(['slug' => 'privacy-policy']);

    $inline = $this->get('/governance/privacy-policy/file')->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");

    expect($inline->headers->get('Content-Disposition'))->toStartWith('inline')->toContain('privacy-policy.pdf');

    $download = $this->get('/governance/privacy-policy/download')->assertOk()->assertHeader('X-Frame-Options', 'DENY');
    expect($download->headers->get('Content-Disposition'))->toStartWith('attachment');

    // وكل صفحة أخرى تبقى غير قابلة للتضمين، ومنها صفحة الخطأ على مسار الملف نفسه
    $this->get('/governance/privacy-policy')->assertHeader('X-Frame-Options', 'DENY');
    $this->get('/governance/unknown/file')->assertNotFound()->assertHeader('X-Frame-Options', 'DENY');
    expect($this->get('/governance')->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});

it('answers 404 for an unpublished, deleted or unknown document, and for its file', function () {
    storedDocument(['slug' => 'draft', 'is_published' => false]);
    storedDocument(['slug' => 'removed'])->delete();

    foreach (['draft', 'removed', 'unknown'] as $slug) {
        $this->get('/governance/'.$slug)->assertNotFound();
        $this->get('/governance/'.$slug.'/file')->assertNotFound();
        $this->get('/governance/'.$slug.'/download')->assertNotFound();
    }

    $this->get('/governance/Bad_Slug')->assertNotFound();
});

it('stops serving the file and the page as soon as the document is unpublished', function () {
    $document = storedDocument(['slug' => 'report']);
    $this->get('/governance/report')->assertOk();
    $this->get('/governance/report/file')->assertOk();

    $document->update(['is_published' => false]);

    $this->get('/governance/report')->assertNotFound();
    $this->get('/governance/report/file')->assertNotFound();
});

it('caches the full list and refreshes it on change, but never caches a search', function () {
    $document = storedDocument(['title' => 'العنوان الأول']);
    $this->get('/governance')->assertSee('العنوان الأول');

    DB::table('documents')->where('id', $document->id)->update(['title' => 'عنوان لم يُعتمد']);

    $this->get('/governance')->assertSee('العنوان الأول');
    $this->get('/governance?q=لم')->assertSee('عنوان لم يُعتمد');

    $document->refresh()->update(['title' => 'العنوان الجديد']);

    $this->get('/governance')->assertSee('العنوان الجديد');
});

it('shows the governance shortcut on the home page now that the section exists', function () {
    $this->get('/')->assertSee(__('home.shortcuts.governance.title'))->assertSee('href="'.route('governance.index').'"', false);
});

it('shows the file size in kilobytes below a megabyte, and in megabytes above', function () {
    storedDocument(['slug' => 'small', 'file_size' => 300 * 1024]);
    storedDocument(['slug' => 'large', 'file_size' => 3 * 1048576]);

    $this->get('/governance/small')->assertSee(__('site.file.size_kb', ['size' => 300]));
    $this->get('/governance/large')->assertSee(__('site.file.size_mb', ['size' => '3.0']));
});

// ملاحظات التجربة الشاملة (الخلل 8): الأبيض بشفافية 80% على الأزرق 4.4:1 دون حد WCAG، وبلا شفافية 5.9:1
it('shows the file size on the download button in full white, for a contrast of at least 4.5:1', function () {
    storedDocument(['slug' => 'bylaws']);

    $html = (string) $this->get('/governance/bylaws')->assertOk()->getContent();
    $button = (string) str($html)->after('href="'.route('governance.download', 'bylaws').'"')->before('</a>');

    // الحجم يرث الأبيض الكامل من الزر، بلا شفافية ولا لون أخف
    expect($button)->toContain('bg-primary text-white')
        ->toContain('<span class="font-normal">('.__('site.file.size_kb', ['size' => 1]).')</span>');
});

it('opens a link document in a new tab, with no embedded viewer and no download', function () {
    Document::factory()->link('https://example.org/annual-report')->create(['slug' => 'annual-report', 'title' => 'التقرير السنوي']);

    $this->get('/governance/annual-report')->assertOk()
        ->assertSee('href="https://example.org/annual-report"', false)
        ->assertSee('target="_blank" rel="noopener noreferrer"', false)
        ->assertSee(__('governance.document.open_link'))
        ->assertDontSee('<iframe', false)
        ->assertDontSee(route('governance.download', 'annual-report'), false)
        ->assertDontSee(__('governance.document.mobile_hint'));

    $this->get('/governance')->assertOk()
        ->assertSee(route('governance.show', 'annual-report'), false)
        ->assertDontSee(route('governance.download', 'annual-report'), false);

    // ولا ملف يُقدَّم لوثيقة الرابط
    $this->get('/governance/annual-report/file')->assertNotFound();
    $this->get('/governance/annual-report/download')->assertNotFound();
});

it('keeps an unpublished link document and its link out of the site', function () {
    Document::factory()->link('https://example.org/draft-report')->unpublished()->create(['slug' => 'draft-report']);

    $this->get('/governance/draft-report')->assertNotFound();
    $this->get('/governance')->assertOk()->assertDontSee('example.org/draft-report', false)->assertDontSee('/governance/draft-report', false);
});

it('refuses a document with neither a file nor a link, or with both, in the database too', function () {
    expect(fn () => Document::factory()->create(['file' => null, 'url' => null]))->toThrow(QueryException::class)
        ->and(fn () => Document::factory()->create(['url' => 'https://example.org/report']))->toThrow(QueryException::class);
});

it('shows no link written to the database without its checks', function () {
    $document = Document::factory()->link()->create(['slug' => 'raw-link']);
    DB::table('documents')->where('id', $document->id)->update(['url' => 'javascript:alert(1)']);

    $this->get('/governance/raw-link')->assertOk()->assertDontSee('javascript:', false)->assertDontSee('<iframe', false);
});
