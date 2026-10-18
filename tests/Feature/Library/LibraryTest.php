<?php

use App\Enums\LibrarySection;
use App\Models\LibraryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(fn () => Storage::fake('local'));

/**
 * عنصر بملفه في القرص الخاص: كتاب PDF افتراضياً، أو صوت إن كان القسم الصوتيات.
 *
 * @param  array<string, mixed>  $attributes
 */
function storedLibraryItem(array $attributes = []): LibraryItem
{
    $item = LibraryItem::factory()->create($attributes);
    Storage::disk('local')->put((string) $item->file, $item->section->takesPdf() ? pdfContents() : mp3Contents());

    return $item;
}

it('shows the four sections and the latest published items, without drafts', function () {
    storedLibraryItem(['title' => 'كتاب منشور']);
    LibraryItem::factory()->video()->create(['title' => 'مقطع منشور']);
    storedLibraryItem(['title' => 'مسودة لم تُنشر', 'is_published' => false]);

    $response = $this->get('/library')->assertOk()
        ->assertSeeInOrder(['<h1', __('library.title')], false)
        ->assertSee('كتاب منشور')
        ->assertSee('مقطع منشور')
        ->assertDontSee('مسودة لم تُنشر');

    foreach (LibrarySection::cases() as $section) {
        $response->assertSee('href="'.route('library.section', $section->value).'"', false)->assertSee($section->getLabel());
    }
});

it('lists the published items of the section only, newest first and undated last', function () {
    storedLibraryItem(['title' => 'كتاب أقدم', 'published_on' => '2020-01-01']);
    storedLibraryItem(['title' => 'كتاب أحدث', 'published_on' => '2025-01-01']);
    storedLibraryItem(['title' => 'كتاب بلا تاريخ', 'published_on' => null]);
    storedLibraryItem(['title' => 'دراسة عن الرتق', 'section' => LibrarySection::Research]);

    $this->get('/library/books')->assertOk()
        ->assertSeeInOrder(['<h1', __('library.sections.books')], false)
        ->assertSee('aria-current="page"', false)
        ->assertSeeInOrder(['كتاب أحدث', 'كتاب أقدم', 'كتاب بلا تاريخ'])
        ->assertDontSee('دراسة عن الرتق');
});

it('searches by title or author and filters by tag, taking % and _ as plain letters', function () {
    storedLibraryItem(['title' => 'مراحل خلق الجنين', 'author' => 'المتحدث الأول', 'tags' => ['الأجنة']]);
    storedLibraryItem(['title' => 'الرتق والفتق', 'author' => 'المتحدث الثاني', 'tags' => ['الفلك']]);

    $this->get('/library/books')->assertSee('href="'.route('library.section', ['books', 'tag' => 'الفلك']).'"', false);
    // وروابط التصنيفات في نتيجة البحث تحفظ كلمته
    $this->get('/library/books?q=الرتق')->assertSee('href="'.e(route('library.section', ['books', 'tag' => 'الفلك', 'q' => 'الرتق'])).'"', false);

    $this->get('/library/books?q=الجنين')->assertSee('مراحل خلق الجنين')->assertDontSee('الرتق والفتق');
    $this->get('/library/books?q=الثاني')->assertSee('الرتق والفتق')->assertDontSee('مراحل خلق الجنين');
    $this->get('/library/books?tag=الفلك')->assertSee('الرتق والفتق')->assertDontSee('مراحل خلق الجنين');
    $this->get('/library/books?q=%25')->assertSee(__('library.list.no_results'));
    $this->get('/library/books?q=_')->assertSee(__('library.list.no_results'));
});

it('sends an invalid filter back to the section', function (string $query) {
    $this->get('/library/audio?'.$query)->assertRedirect(route('library.section', 'audio'));
})->with(['q='.str_repeat('x', 101), 'tag='.str_repeat('x', 41), 'q[]=x', 'page=0', 'page=abc', 'q=%FF', 'tag=x%00y']);

it('answers 404 for an unknown section and for a page after the last one', function () {
    storedLibraryItem(['title' => 'الكتاب الوحيد']);

    $this->get('/library/novels')->assertNotFound();
    $this->get('/library/books?page=2')->assertNotFound();
    $this->get('/library/books?q=الوحيد&page=2')->assertNotFound();
    $this->get('/library/books?page=1')->assertOk()->assertSee('الكتاب الوحيد');
});

it('loads a YouTube video from youtube-nocookie only when pressed, and plays a hosted file in the browser player', function () {
    config(['security.csp.media_src' => ['https://media.ejaz.sa']]);
    LibraryItem::factory()->video('https://youtu.be/abcdefghijk?si=share')->create(['slug' => 'embryo', 'title' => 'مراحل خلق الجنين']);
    LibraryItem::factory()->video('https://media.ejaz.sa/videos/clip.mp4')->create(['slug' => 'hosted']);

    // لا إطار ليوتيوب قبل الضغط، فلا يتصل متصفح الزائر بخوادمه. وبلا JavaScript يفتح الرابط المقطع هناك
    $page = $this->get('/library/videos/embryo')->assertOk()
        ->assertSeeInOrder(['<h1', 'مراحل خلق الجنين'], false)
        ->assertSee('href="https://www.youtube.com/watch?v=abcdefghijk"', false)
        ->assertSee('data-video-embed="https://www.youtube-nocookie.com/embed/abcdefghijk?autoplay=1"', false);

    expect($page->getContent())->not->toContain('<iframe');

    $this->get('/library/videos/hosted')->assertOk()
        ->assertSee('<video src="https://media.ejaz.sa/videos/clip.mp4"', false);
});

it('plays an audio file from its route, and shows a book in its viewer with a download link', function () {
    storedLibraryItem(['slug' => 'lecture', 'section' => LibrarySection::Audio, 'file' => 'library/lecture.mp3']);
    storedLibraryItem(['slug' => 'book', 'description' => "السطر الأول\nالسطر الثاني"]);

    $this->get('/library/audio/lecture')->assertOk()
        ->assertSee('<audio src="'.route('library.file', ['audio', 'lecture']).'"', false);

    $this->get('/library/books/book')->assertOk()
        ->assertSee('<iframe src="'.route('library.file', ['books', 'book']).'"', false)
        ->assertSee('href="'.route('library.download', ['books', 'book']).'"', false)
        ->assertSee("السطر الأول\nالسطر الثاني");
});

it('serves the file with its type, frames only a PDF from the site, and answers a range request', function () {
    storedLibraryItem(['slug' => 'book']);
    storedLibraryItem(['slug' => 'lecture', 'section' => LibrarySection::Audio, 'file' => 'library/lecture.mp3']);

    // PDF كاملاً لا أجزاءً، فلا يتجاوز عارض المتصفح حد الطلبات
    $pdf = $this->get('/library/books/book/file')->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");

    expect($pdf->headers->get('Content-Disposition'))->toStartWith('inline')->toContain('book.pdf')
        ->and($pdf->headers->has('Accept-Ranges'))->toBeFalse();

    // الصوت لا يُضمَّن، ويُطلب أجزاءً عند التقديم والتأخير
    $audio = $this->get('/library/audio/lecture/file')->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Accept-Ranges', 'bytes');

    $this->get('/library/audio/lecture/file', ['Range' => 'bytes=0-9'])
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 0-9/'.strlen(mp3Contents()));

    $download = $this->get('/library/books/book/download')->assertOk();

    expect($download->headers->get('Content-Disposition'))->toStartWith('attachment')->toContain('book.pdf');

    // ولا يحتفظ وسيط مشترك بملف قد يُلغى نشره
    foreach ([$pdf, $audio, $download, $this->get('/library/audio/lecture/download')] as $response) {
        expect($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public');
    }
});

it('pages through a section, keeping the validated filter in the page links and nothing else', function () {
    LibraryItem::factory()->count(13)->video()->create(['tags' => ['الفلك']]);

    $this->get('/library/videos?tag=الفلك')->assertOk()
        ->assertSee(route('library.section', ['videos', 'tag' => 'الفلك', 'page' => 2]))
        ->assertSee(__('site.page_of', ['page' => 1, 'pages' => 2]));

    $this->get('/library/videos?tag=الفلك&page=2')->assertOk()->assertSee(__('site.page_of', ['page' => 2, 'pages' => 2]));

    // معامل لم يجتز التحقق لا يدخل روابط الصفحة الأولى المخزنة
    expect($this->get('/library/videos?utm=evil')->assertOk()->getContent())->not->toContain('utm');
    expect($this->get('/library/videos')->getContent())->not->toContain('utm');
});

it('answers 404 for an unpublished, deleted or unknown item and its file, and under another section', function () {
    storedLibraryItem(['slug' => 'draft', 'is_published' => false]);
    storedLibraryItem(['slug' => 'removed'])->delete();
    storedLibraryItem(['slug' => 'book']);
    LibraryItem::factory()->video()->create(['slug' => 'clip']);

    foreach (['draft', 'removed', 'unknown'] as $slug) {
        $this->get('/library/books/'.$slug)->assertNotFound();
        $this->get('/library/books/'.$slug.'/file')->assertNotFound();
        $this->get('/library/books/'.$slug.'/download')->assertNotFound();
    }

    $this->get('/library/research/book')->assertNotFound();
    $this->get('/library/research/book/file')->assertNotFound();
    $this->get('/library/videos/clip/file')->assertNotFound()->assertHeader('X-Frame-Options', 'DENY');
    $this->get('/library/books/Bad_Slug')->assertNotFound();
});

it('stops serving the item and its file as soon as it is unpublished', function () {
    $item = storedLibraryItem(['slug' => 'book']);
    $this->get('/library/books/book')->assertOk();
    $this->get('/library/books/book/file')->assertOk();

    $item->update(['is_published' => false]);

    $this->get('/library/books/book')->assertNotFound();
    $this->get('/library/books/book/file')->assertNotFound();
});

it('shows related items from the same section, those sharing a tag first, never itself or a draft', function () {
    storedLibraryItem(['slug' => 'main', 'title' => 'العنصر الرئيسي', 'tags' => ['الفلك']]);
    storedLibraryItem(['title' => 'يشارك التصنيف', 'tags' => ['الفلك'], 'published_on' => '2001-01-01']);
    storedLibraryItem(['title' => 'أحدث بلا تصنيف مشترك', 'published_on' => '2026-01-01']);
    storedLibraryItem(['title' => 'من قسم آخر', 'section' => LibrarySection::Research, 'tags' => ['الفلك']]);
    storedLibraryItem(['title' => 'مسودة تشارك التصنيف', 'tags' => ['الفلك'], 'is_published' => false]);

    $response = $this->get('/library/books/main')->assertOk()
        ->assertSee(__('library.item.related'))
        ->assertSeeInOrder(['يشارك التصنيف', 'أحدث بلا تصنيف مشترك'])
        ->assertDontSee('من قسم آخر')
        ->assertDontSee('مسودة تشارك التصنيف');

    // والعنصر نفسه ليس في قائمته
    expect(Str::after((string) $response->getContent(), __('library.item.related')))->not->toContain('العنصر الرئيسي');
});

it('caches the section and refreshes it on change, but never caches a search', function () {
    $item = storedLibraryItem(['title' => 'العنوان الأول']);
    $this->get('/library/books')->assertSee('العنوان الأول');

    DB::table('library_items')->where('id', $item->id)->update(['title' => 'عنوان لم يُعتمد']);

    $this->get('/library/books')->assertSee('العنوان الأول');
    $this->get('/library/books?q=لم')->assertSee('عنوان لم يُعتمد');

    $item->refresh()->update(['title' => 'العنوان الجديد']);

    $this->get('/library/books')->assertSee('العنوان الجديد');
});

// ملاحظات التجربة الشاملة (الخلل 8): حجم الملف على زر التنزيل بتباين 4.5:1 فأكثر، كالحوكمة
it('shows the file size on the download button in full white', function () {
    storedLibraryItem(['slug' => 'book']);

    $html = (string) $this->get('/library/books/book')->assertOk()->getContent();
    $button = (string) str($html)->after('href="'.route('library.download', ['books', 'book']).'"')->before('</a>');

    // الحجم يرث الأبيض الكامل من الزر، بلا شفافية ولا لون أخف
    expect($button)->toContain('bg-primary text-white')
        ->toContain('<span class="font-normal">('.__('site.file.size_kb', ['size' => 1]).')</span>');
});
