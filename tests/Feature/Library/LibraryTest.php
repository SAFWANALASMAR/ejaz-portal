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

    $this->get('/library/books?q=الجنين')->assertSee('مراحل خلق الجنين')->assertDontSee('الرتق والفتق');
    $this->get('/library/books?q=الثاني')->assertSee('الرتق والفتق')->assertDontSee('مراحل خلق الجنين');
    $this->get('/library/books?tag=الفلك')->assertSee('الرتق والفتق')->assertDontSee('مراحل خلق الجنين');
    $this->get('/library/books?q=%25')->assertSee(__('library.list.no_results'));
    $this->get('/library/books?q=_')->assertSee(__('library.list.no_results'));
});

it('sends an invalid filter back to the section', function (string $query) {
    $this->get('/library/audio?'.$query)->assertRedirect(route('library.section', 'audio'));
})->with(['q='.str_repeat('x', 101), 'tag='.str_repeat('x', 41), 'q[]=x', 'page=0', 'page=abc']);

it('answers 404 for an unknown section and for a page after the last one', function () {
    storedLibraryItem(['title' => 'الكتاب الوحيد']);

    $this->get('/library/novels')->assertNotFound();
    $this->get('/library/books?page=2')->assertNotFound();
    $this->get('/library/books?q=الوحيد&page=2')->assertNotFound();
    $this->get('/library/books?page=1')->assertOk()->assertSee('الكتاب الوحيد');
});

it('embeds a YouTube video from youtube-nocookie, and plays a hosted file in the browser player', function () {
    config(['security.csp.media_src' => ['https://media.ejaz.sa']]);
    LibraryItem::factory()->video('https://youtu.be/abcdefghijk?si=share')->create(['slug' => 'embryo', 'title' => 'مراحل خلق الجنين']);
    LibraryItem::factory()->video('https://media.ejaz.sa/videos/clip.mp4')->create(['slug' => 'hosted']);

    $this->get('/library/videos/embryo')->assertOk()
        ->assertSeeInOrder(['<h1', 'مراحل خلق الجنين'], false)
        ->assertSee('src="https://www.youtube-nocookie.com/embed/abcdefghijk"', false);

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

    $pdf = $this->get('/library/books/book/file')->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");

    expect($pdf->headers->get('Content-Disposition'))->toStartWith('inline')->toContain('book.pdf');

    // الصوت لا يُضمَّن، ويُطلب أجزاءً عند التقديم والتأخير
    $this->get('/library/audio/lecture/file')->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Accept-Ranges', 'bytes');

    $this->get('/library/audio/lecture/file', ['Range' => 'bytes=0-9'])
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 0-9/'.strlen(mp3Contents()));

    $download = $this->get('/library/books/book/download')->assertOk();

    expect($download->headers->get('Content-Disposition'))->toStartWith('attachment')->toContain('book.pdf');
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
