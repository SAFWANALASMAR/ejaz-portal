<?php

use App\Actions\Library\SaveLibraryItem;
use App\Enums\LibrarySection;
use App\Enums\RoleName;
use App\Filament\Resources\LibraryItems\Pages\EditLibraryItem;
use App\Models\LibraryItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| كتب المكتبة «للقراءة فقط — بلا تحميل» (طلب المدير التنفيذي، 30 أكتوبر 2026، docs/decisions.md): لا زر تنزيل ولا فتح،
| والملف inline بلا تخزين لقارئ الصفحة وحده، ولا تنزيل بمسار ولا بمعامل، والنشر يحكمه كما كان. والكتاب القابل للتنزيل
| كما هو.
*/

beforeEach(fn () => Storage::fake('local'));

/**
 * @param  array<string, mixed>  $attributes
 */
function readOnlyBook(array $attributes = []): LibraryItem
{
    $item = LibraryItem::factory()->create(['slug' => 'book', 'title' => 'كتاب للقراءة', 'is_downloadable' => false, ...$attributes]);
    Storage::disk(LibraryItem::disk())->put((string) $item->file, pdfContents());

    return $item;
}

it('keeps every existing and new item downloadable by default, with its buttons, viewer and attachment', function () {
    $item = LibraryItem::factory()->create(['slug' => 'book']);
    Storage::disk('local')->put((string) $item->file, pdfContents());

    expect($item->fresh()->is_downloadable)->toBeTrue()->and($item->fresh()->isReadOnly())->toBeFalse();

    $this->get('/library/books/book')->assertOk()
        ->assertSee('href="'.route('library.download', ['books', 'book']).'"', false)
        ->assertSee('<iframe src="'.route('library.file', ['books', 'book']).'"', false)
        ->assertDontSee('data-pdf-reader', false)
        ->assertDontSee(__('library.item.read_only'));

    expect($this->get('/library/books/book/download')->assertOk()->headers->get('Content-Disposition'))->toStartWith('attachment');
    $this->get('/library/books/book/file')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('shows a read-only book in the canvas reader, with no download or open button and no browser viewer', function () {
    readOnlyBook();

    $response = $this->get('/library/books/book');

    $response->assertOk()
        ->assertSee('data-pdf-reader="'.route('library.file', ['books', 'book']).'"', false)
        ->assertSee('<canvas data-pdf-canvas', false)
        ->assertSee('data-pdf-next', false)
        ->assertSee('data-pdf-page', false)
        ->assertSee('data-pdf-zoom-in', false)
        ->assertSee(__('library.item.read_only'))
        ->assertDontSee(route('library.download', ['books', 'book']))
        ->assertDontSee('<iframe', false)
        ->assertDontSee(__('library.item.download'))
        ->assertDontSee(__('library.item.open'));

    // لا رابط بتبويب جديد في المحتوى (والتذييل فيه رابط المتجر الخارجي بتبويب جديد منذ 1 نوفمبر 2026)
    expect((string) str($response->getContent())->after('<main')->before('</main>'))->not->toContain('target="_blank"');

    // والبطاقة في القسم بعلامتها، بلا زر تنزيل
    $this->get('/library/books')->assertOk()
        ->assertSee(__('library.item.read_only_short'))
        ->assertDontSee(route('library.download', ['books', 'book']));
});

it('serves a read-only file inline without storing it, under the full policy, whatever the query says', function (string $query) {
    readOnlyBook();

    $response = $this->get('/library/books/book/file'.$query)->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('X-Frame-Options', 'DENY');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline')->not->toContain('attachment')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')->not->toContain('public')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'")->toContain("default-src 'self'")
        ->and($response->streamedContent())->toBe(pdfContents());
})->with(['', '?download=1', '?response-content-disposition=attachment']);

it('refuses to download a read-only book, by its route or any parameter', function () {
    readOnlyBook();

    $this->get('/library/books/book/download')->assertNotFound();
    $this->get('/library/books/book/download?inline=1')->assertNotFound();
});

it('sends a tab or frame opening the read-only file back to the book page, and serves the reader fetch', function (string $destination) {
    readOnlyBook();

    $this->get('/library/books/book/file', ['Sec-Fetch-Dest' => $destination])->assertRedirect(route('library.show', ['books', 'book']));
    $this->get('/library/books/book/file', ['Sec-Fetch-Dest' => 'empty'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
})->with(['document', 'iframe', 'frame', 'embed', 'object']);

it('keeps the publish rule: no page, file or download for an unpublished, deleted or unpublished-later read-only book', function () {
    readOnlyBook(['slug' => 'draft', 'is_published' => false]);
    readOnlyBook(['slug' => 'removed'])->delete();
    $book = readOnlyBook();

    foreach (['draft', 'removed'] as $slug) {
        $this->get('/library/books/'.$slug)->assertNotFound();
        $this->get('/library/books/'.$slug.'/file')->assertNotFound();
        $this->get('/library/books/'.$slug.'/download')->assertNotFound();
    }

    $this->get('/library/books/book/file')->assertOk();
    $book->update(['is_published' => false]);

    $this->get('/library/books/book')->assertNotFound();
    $this->get('/library/books/book/file')->assertNotFound();
});

it('streams a read-only book from S3 without signing a storage link, while a downloadable one keeps its signed redirect', function () {
    config(['filesystems.disks.content-s3' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/content-s3')]]);
    $disk = Storage::fake('content-s3');
    config(['filesystems.disks.content-s3.driver' => 's3', 'filesystems.private_disk' => 'content-s3']);

    $signed = new ArrayObject;
    $disk->buildTemporaryUrlsUsing(function (string $path) use ($signed) {
        $signed[] = $path;

        return 'https://storage.example.test/'.$path.'?signature=x';
    });

    readOnlyBook();
    $open = LibraryItem::factory()->create(['slug' => 'open-book']);
    $disk->put((string) $open->file, pdfContents());

    $response = $this->get('/library/books/book/file')->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Location'))->toBeNull()
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($signed->getArrayCopy())->toBe([]);

    $this->get('/library/books/book/download')->assertNotFound();
    $this->get('/library/books/open-book/file')->assertRedirect('https://storage.example.test/'.$open->file.'?signature=x');
});

it('lets the editor make a book read-only from the form, logged, and keeps the option off audio and videos', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    $book = LibraryItem::factory()->create();
    Storage::disk('local')->put((string) $book->file, pdfContents());

    // الصفحة والقسم مخزّنان قبل التغيير، ويتبدّلان فور الحفظ
    $this->get('/library/books/'.$book->slug)->assertSee('<iframe', false);
    $this->get('/library/books')->assertDontSee(__('library.item.read_only_short'));

    $this->actingAs($editor);
    Livewire::test(EditLibraryItem::class, ['record' => $book->getRouteKey()])
        ->assertFormFieldExists('is_downloadable')
        ->fillForm(['is_downloadable' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($book->fresh()->isReadOnly())->toBeTrue()
        ->and(Activity::query()->where('subject_id', $book->getKey())->latest('id')->first()?->attribute_changes['attributes'])->toHaveKey('is_downloadable', false);

    $this->get('/library/books/'.$book->slug)->assertSee('data-pdf-reader', false)->assertDontSee('<iframe', false)
        ->assertDontSee(route('library.download', ['books', $book->slug]));
    $this->get('/library/books')->assertSee(__('library.item.read_only_short'));

    // الصوت والمرئي لا يأخذان الخيار، ولو مُرّر إلى الإجراء
    $video = app(SaveLibraryItem::class)->handle($editor, new LibraryItem, [
        'title' => 'مقطع', 'slug' => 'clip', 'section' => LibrarySection::Videos->value,
        'video_url' => 'https://www.youtube.com/watch?v=abcdefghijk', 'is_published' => true, 'is_downloadable' => false,
    ]);

    expect($video->fresh()->is_downloadable)->toBeTrue()->and($video->isReadOnly())->toBeFalse();
});

it('refuses the read-only change to anyone without the right to edit the library', function () {
    $book = LibraryItem::factory()->create();

    expect(fn () => app(SaveLibraryItem::class)->handle(userWithRole(RoleName::Secretary), $book, ['is_downloadable' => false]))
        ->toThrow(AuthorizationException::class)
        ->and($book->fresh()->is_downloadable)->toBeTrue();
});

it('limits how often one address streams read-only books, more tightly than the file route', function () {
    readOnlyBook();

    for ($i = 0; $i < 20; $i++) {
        $this->get('/library/books/book/file')->assertOk();
    }

    $this->get('/library/books/book/file')->assertStatus(429);
});

it('counts the reader limit per subscriber, so IPv6 addresses of one /64 share it and another /64 does not', function () {
    readOnlyBook();

    for ($i = 0; $i < 20; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:abcd:1::'.dechex($i + 1)])->get('/library/books/book/file')->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:abcd:1:ffff::9'])->get('/library/books/book/file')->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:abcd:2::1'])->get('/library/books/book/file')->assertOk();
});
