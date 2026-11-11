<?php

use App\Enums\DocumentCategory;
use App\Models\Document;
use App\Models\LibraryItem;
use App\Support\ContentCache;
use App\Support\ReaderFileResponse;
use App\Support\ReaderTicket;
use Illuminate\Cache\ArrayStore;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
| حماية ملفات «للقراءة فقط» من برامج التحميل (قرار صفوان، 10 نوفمبر 2026 — docs/decisions/2026-11-10-feat-reader-download-protection.md):
| رمز لمرة واحدة للجلسة والعنصر مدته خمس دقائق، وترويسة القارئ وطلب fetch من الموقع نفسه، والبايتات مخلوطة بمفتاح الرمز بنوع
| application/octet-stream. لكتب المكتبة ووثائق الحوكمة «للاطلاع فقط» ودليل البراهين، والكتاب القابل للتنزيل كما هو.
*/

beforeEach(fn () => Storage::fake('local'));

/**
 * @param  array<string, mixed>  $attributes
 */
function protectedBook(array $attributes = [], ?string $contents = null): LibraryItem
{
    $item = LibraryItem::factory()->create(['slug' => 'book', 'title' => 'كتاب للقراءة', 'is_downloadable' => false, ...$attributes]);
    Storage::disk(LibraryItem::disk())->put((string) $item->file, $contents ?? pdfContents());

    return $item;
}

function viewOnlyDocument(string $slug = 'policy'): Document
{
    $document = Document::factory()->create(['slug' => $slug, 'title' => 'وثيقة للاطلاع', 'category' => DocumentCategory::Policies, 'is_view_only' => true]);
    Storage::disk(Document::disk())->put((string) $document->file, pdfContents());

    return $document;
}

/**
 * الرمز والمفتاح من صفحة العنصر، مع كوكي جلستها، كما يجدها القارئ.
 *
 * @return array{session: string, ticket: string, key: string}
 */
function ticketFromPage(TestResponse $page): array
{
    $html = (string) $page->getContent();
    preg_match('/data-pdf-ticket="([0-9a-f]{64})" data-pdf-key="([A-Za-z0-9+\/=]+)"/', $html, $match);

    expect($match)->toHaveCount(3);

    return ['session' => (string) $page->getCookie((string) config('session.cookie'))?->getValue(), 'ticket' => $match[1], 'key' => $match[2]];
}

it('gives the book page a one-time ticket and key, and the reader gets mixed bytes that unmix to the original PDF', function () {
    protectedBook();

    $page = $this->get('/library/books/book')->assertOk()
        ->assertSee('data-pdf-reader="'.route('library.file', ['books', 'book']).'"', false)
        ->assertSee('data-pdf-ticket-url="'.route('library.reader-ticket', ['books', 'book']).'"', false)
        ->assertDontSee('data-pdf-ticket-slot', false);
    $ticket = ticketFromPage($page);

    $response = readerFetch('/library/books/book/file', $ticket)->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Length', (string) strlen(pdfContents()));
    $bytes = $response->streamedContent();

    expect($response->headers->has('Content-Disposition'))->toBeFalse()
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')->not->toContain('public')
        ->and($bytes)->not->toStartWith('%PDF')->not->toContain('/Type /Catalog')
        ->and(strlen($bytes))->toBe(strlen(pdfContents()))
        ->and(readerUnmix($bytes, $ticket))->toBe(pdfContents());
});

it('gives each page view its own ticket and key, so the same file is mixed differently each time', function () {
    $book = protectedBook();

    $first = ticketFromPage($this->get('/library/books/book'));
    $second = readerTicket($book);

    expect($first['ticket'])->not->toBe($second['ticket'])->and($first['key'])->not->toBe($second['key'])
        ->and(strlen((string) base64_decode($first['key'], true)))->toBe(32);

    $a = readerFetch('/library/books/book/file', $first)->assertOk()->streamedContent();
    $b = readerFetch('/library/books/book/file', $second)->assertOk()->streamedContent();

    expect($a)->not->toBe($b)->and(readerUnmix($a, $first))->toBe(readerUnmix($b, $second));
});

it('unmixes a file larger than one stream chunk byte for byte', function () {
    $contents = pdfContents(str_repeat("q 1 0 0 1 0 0 cm Q\n", 12000).random_bytes(997));
    $book = protectedBook(contents: $contents);
    $ticket = readerTicket($book);

    expect(strlen($contents))->toBeGreaterThan(65536 * 3)
        ->and(readerUnmix(readerFetch('/library/books/book/file', $ticket)->assertOk()->streamedContent(), $ticket))->toBe($contents);
});

it('refuses the file without a ticket, with a malformed one, or with a made-up one', function () {
    $book = protectedBook();

    $this->get('/library/books/book/file', [ReaderFileResponse::HEADER => '1'])->assertNotFound();
    $this->get('/library/books/book/file?t=abc', [ReaderFileResponse::HEADER => '1'])->assertNotFound();
    $this->get('/library/books/book/file?t[]=x', [ReaderFileResponse::HEADER => '1'])->assertNotFound();

    readerFetch('/library/books/book/file', [...readerTicket($book), 'ticket' => str_repeat('a', 64)])->assertNotFound();
});

it('accepts a ticket once only', function () {
    $ticket = readerTicket(protectedBook());

    readerFetch('/library/books/book/file', $ticket)->assertOk();
    readerFetch('/library/books/book/file', $ticket)->assertNotFound();
});

it('refuses a ticket from another session', function () {
    $ticket = readerTicket(protectedBook());

    readerFetch('/library/books/book/file', [...$ticket, 'session' => Str::random(40)])->assertNotFound();
    // والرمز يُحذف عند أول محاولة أياً كانت، فلا يُجرَّب على جلسات كثيرة، والقارئ يطلب غيره (fetchPdf)
    readerFetch('/library/books/book/file', $ticket)->assertNotFound();
});

it('accepts a ticket within five minutes and refuses it after', function () {
    $book = protectedBook();
    $early = readerTicket($book);
    $late = readerTicket($book);

    $this->travel(ReaderTicket::TTL_SECONDS - 10)->seconds();
    readerFetch('/library/books/book/file', $early)->assertOk();

    $this->travel(20)->seconds();
    readerFetch('/library/books/book/file', $late)->assertNotFound();

    expect(ReaderTicket::TTL_SECONDS)->toBe(300);
});

it('refuses a ticket for another item', function () {
    protectedBook();
    $other = protectedBook(['slug' => 'other-book']);

    readerFetch('/library/books/book/file', readerTicket($other))->assertNotFound();
    readerFetch('/library/books/other-book/file', readerTicket($other))->assertOk();
});

it('refuses the file without the reader header, even with a valid ticket, and keeps the ticket unspent', function () {
    $ticket = readerTicket(protectedBook());

    readerFetch('/library/books/book/file', $ticket, [])->assertNotFound();
    readerFetch('/library/books/book/file', $ticket, [ReaderFileResponse::HEADER => '0'])->assertNotFound();
    readerFetch('/library/books/book/file', $ticket)->assertOk();
});

it('refuses a request the browser marks as cross-site, same-site or not a fetch, and lets a browser without Sec-Fetch through', function (array $headers, int $status) {
    $ticket = readerTicket(protectedBook());

    readerFetch('/library/books/book/file', $ticket, [ReaderFileResponse::HEADER => '1', ...$headers])->assertStatus($status);
})->with([
    'cross-site' => [['Sec-Fetch-Site' => 'cross-site', 'Sec-Fetch-Dest' => 'empty'], 404],
    'same-site' => [['Sec-Fetch-Site' => 'same-site', 'Sec-Fetch-Dest' => 'empty'], 404],
    'typed in the address bar' => [['Sec-Fetch-Site' => 'none', 'Sec-Fetch-Dest' => 'empty'], 404],
    'as an image' => [['Sec-Fetch-Site' => 'same-origin', 'Sec-Fetch-Dest' => 'image'], 404],
    'as a script' => [['Sec-Fetch-Site' => 'same-origin', 'Sec-Fetch-Dest' => 'script'], 404],
    'the reader fetch' => [['Sec-Fetch-Site' => 'same-origin', 'Sec-Fetch-Dest' => 'empty', 'Sec-Fetch-Mode' => 'cors'], 200],
    'Safari before 16.4' => [[], 200],
]);

it('still sends a tab or frame opening the file back to the book page', function (string $destination) {
    protectedBook();

    $this->get('/library/books/book/file', ['Sec-Fetch-Dest' => $destination])->assertRedirect(route('library.show', ['books', 'book']));
})->with(['document', 'iframe', 'frame', 'embed', 'object']);

it('refuses HEAD without a ticket, so a download manager cannot probe the file', function () {
    protectedBook();

    $this->call('HEAD', '/library/books/book/file')->assertNotFound();
});

it('refuses HEAD even with a valid ticket, and leaves that ticket for the reader', function () {
    $ticket = readerTicket(protectedBook());

    readerFetch('/library/books/book/file', $ticket, method: 'HEAD')->assertNotFound();
    readerFetch('/library/books/book/file', $ticket)->assertOk();
});

it('gives two sessions different tickets from one cached page, and neither works for the other', function () {
    protectedBook();
    // النص المخزّن كما هو بلا رسم جديد (إن لم يكن مخزّناً رد null)
    $cached = fn (): mixed => ContentCache::remember('library.show.reader.book', fn (): mixed => null);

    $firstPage = $this->get('/library/books/book')->assertOk();
    $stored = $cached();
    $secondPage = $this->get('/library/books/book')->assertOk();

    // الطلب الثاني من الصفحة المخزّنة نفسها، لا رسم جديد: النص المخزّن بعلامته لم يتغير
    expect($stored)->toBeArray()
        ->and($stored['html'])->toContain(ReaderTicket::SLOT)
        ->and($cached())->toBe($stored);

    $first = ticketFromPage($firstPage);
    $second = ticketFromPage($secondPage);

    expect($first['session'])->not->toBe($second['session'])
        ->and($first['ticket'])->not->toBe($second['ticket']);

    // رمز الأولى بجلسة الثانية، ورمز الثانية بجلسة الأولى: 404 كلاهما
    readerFetch('/library/books/book/file', [...$first, 'session' => $second['session']])->assertNotFound();
    readerFetch('/library/books/book/file', [...$second, 'session' => $first['session']])->assertNotFound();

    // ورمزان جديدان من الصفحة المخزّنة نفسها، كل واحد بجلسته: يعملان
    $freshFirst = ticketFromPage($this->withCookie((string) config('session.cookie'), $first['session'])->get('/library/books/book')->assertOk());
    $freshSecond = ticketFromPage($this->withCookie((string) config('session.cookie'), $second['session'])->get('/library/books/book')->assertOk());

    readerFetch('/library/books/book/file', [...$freshFirst, 'session' => $first['session']])->assertOk();
    readerFetch('/library/books/book/file', [...$freshSecond, 'session' => $second['session']])->assertOk();
    expect($cached())->toBe($stored);
});

it('injects the ticket into the reader only, even when the item title carries the slot text', function () {
    protectedBook(['title' => 'كتاب data-pdf-ticket-slot="" فيه العلامة']);

    $html = (string) $this->get('/library/books/book')->assertOk()->getContent();

    expect(substr_count($html, 'data-pdf-ticket="'))->toBe(1)
        ->and($html)->toMatch('/data-pdf-ticket-url="[^"]+" data-pdf-ticket="[0-9a-f]{64}" data-pdf-key="/')
        ->and($html)->toContain('data-pdf-ticket-slot=&quot;&quot;')
        ->and($html)->not->toContain('data-pdf-ticket-slot=""');
});

it('does not read a page cached before the protection, so the reader always gets its ticket link and slot', function () {
    protectedBook();
    // الصفحة كما خُزّنت قبل النشر بالمفتاح القديم: قارئ بلا رابط رمز ولا علامة
    ContentCache::remember('library.show.book', fn (): array => ['title' => 'كتاب', 'summary' => 'كتاب', 'image' => null, 'html' => '<div data-pdf-reader="/library/books/book/file">قديم</div>']);

    $html = (string) $this->get('/library/books/book')->assertOk()->getContent();

    expect($html)->not->toContain('>قديم</div>')
        ->and($html)->toContain('data-pdf-ticket-url="'.route('library.reader-ticket', ['books', 'book']).'"')
        ->and($html)->toMatch('/data-pdf-ticket="[0-9a-f]{64}"/');
});

it('refuses a ticket when the store does not confirm it deleted it, so a racing request gets nothing', function () {
    Cache::extend('forgetful', fn () => Cache::repository(new class extends ArrayStore
    {
        public function forget($key)
        {
            parent::forget($key);

            return false;
        }
    }));
    config(['cache.stores.forgetful' => ['driver' => 'forgetful']]);
    Cache::setDefaultDriver('forgetful');

    $book = protectedBook();
    $request = Request::create('/library/books/book/file');
    $request->setLaravelSession(app('session')->driver());
    $issued = ReaderTicket::issue($request, ReaderTicket::scope($book));
    $request->query->set('t', $issued['ticket']);

    expect(ReaderTicket::consume($request, ReaderTicket::scope($book)))->toBeNull();
});

it('keeps the per-address limit, counting every file request, with a retry time', function () {
    $book = protectedBook();

    for ($i = 0; $i < ReaderFileResponse::PER_MINUTE; $i++) {
        readerFetch('/library/books/book/file', readerTicket($book))->assertOk();
    }

    $retry = readerFetch('/library/books/book/file', readerTicket($book))->assertStatus(429)->headers->get('Retry-After');
    expect((int) $retry)->toBeGreaterThan(0)->toBeLessThanOrEqual(60);
});

it('issues a fresh ticket by POST for the reader to retry without reloading the page, only for a published read-only item', function () {
    $book = protectedBook();
    $open = LibraryItem::factory()->create(['slug' => 'open-book']);
    Storage::disk(LibraryItem::disk())->put((string) $open->file, pdfContents());
    protectedBook(['slug' => 'draft', 'is_published' => false]);

    $session = Str::random(40);
    $response = $this->withCredentials()->withCookie((string) config('session.cookie'), $session)
        ->postJson('/library/books/book/reader-ticket')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $ticket = ['session' => $session, ...$response->json()];

    expect($ticket['ticket'])->toMatch('/\A[0-9a-f]{64}\z/')
        ->and(readerUnmix(readerFetch('/library/books/book/file', $ticket)->assertOk()->streamedContent(), $ticket))->toBe(pdfContents());

    $this->postJson('/library/books/open-book/reader-ticket')->assertNotFound();
    $this->postJson('/library/books/draft/reader-ticket')->assertNotFound();
    // ولا رمز بطلب GET (رابط يُفتح أو يُلتقط)
    expect($this->get('/library/books/book/reader-ticket')->status())->toBeIn([404, 405])
        ->and($book->fresh()->isReadOnly())->toBeTrue();
});

it('refuses a ticket POST without the CSRF token', function () {
    protectedBook();
    viewOnlyDocument();
    $this->app->singleton(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });

    $this->postJson('/library/books/book/reader-ticket')->assertStatus(419);
    $this->postJson('/governance/policy/reader-ticket')->assertStatus(419);
});

// حماية لارافيل من تزوير الطلب تقبل طلب الموقع نفسه بترويسة المتصفح Sec-Fetch-Site بلا رمز، وترفض الطلب من موقع آخر بلا رمز
// (على الخادم بعد نشر v0.15.7 نجح POST من الصفحة نفسها بلا X-CSRF-TOKEN — المشرف، 11 نوفمبر 2026)
it('accepts a ticket POST from the same origin without a token, and refuses it from another site', function (string $site, int $status) {
    protectedBook();
    viewOnlyDocument();
    $this->app->singleton(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });

    $this->postJson('/library/books/book/reader-ticket', [], ['Sec-Fetch-Site' => $site])->assertStatus($status);
    $this->postJson('/governance/policy/reader-ticket', [], ['Sec-Fetch-Site' => $site])->assertStatus($status);
})->with([
    'cross-site' => ['cross-site', 419],
    'same-site' => ['same-site', 419],
    'same-origin' => ['same-origin', 200],
]);

it('protects a view-only governance document by the same rule, from its page to the unmixed bytes', function () {
    $document = viewOnlyDocument();

    $page = $this->get('/governance/policy')->assertOk()
        ->assertSee('data-pdf-ticket-url="'.route('governance.reader-ticket', 'policy').'"', false);
    $ticket = ticketFromPage($page);

    $response = readerFetch('/governance/policy/file', $ticket)->assertOk()->assertHeader('Content-Type', 'application/octet-stream');
    expect($response->headers->has('Content-Disposition'))->toBeFalse()
        ->and($response->streamedContent())->not->toStartWith('%PDF')
        ->and(readerUnmix($response->streamedContent(), $ticket))->toBe(pdfContents());

    readerFetch('/governance/policy/file', $ticket)->assertNotFound();
    $this->get('/governance/policy/file', [ReaderFileResponse::HEADER => '1'])->assertNotFound();
    readerFetch('/governance/policy/file', readerTicket($document), [])->assertNotFound();
    readerFetch('/governance/policy/file', readerTicket($document), [ReaderFileResponse::HEADER => '1', 'Sec-Fetch-Site' => 'cross-site'])->assertNotFound();

    // ورمز كتاب لا يفتح وثيقة
    readerFetch('/governance/policy/file', readerTicket(protectedBook()))->assertNotFound();

    $fresh = $this->postJson('/governance/policy/reader-ticket')->assertOk()->json();
    expect($fresh)->toHaveKeys(['ticket', 'key']);
});

it('leaves downloadable books, documents that are not view-only, and audio exactly as they were', function () {
    $open = LibraryItem::factory()->create(['slug' => 'open-book']);
    Storage::disk(LibraryItem::disk())->put((string) $open->file, pdfContents());
    $document = Document::factory()->create(['slug' => 'open-doc', 'category' => DocumentCategory::Policies, 'is_view_only' => false]);
    Storage::disk(Document::disk())->put((string) $document->file, pdfContents());
    $audio = LibraryItem::factory()->audio()->create(['slug' => 'talk']);
    Storage::disk(LibraryItem::disk())->put((string) $audio->file, mp3Contents());

    $this->get('/library/books/open-book')->assertOk()->assertDontSee('data-pdf-ticket', false);
    expect($this->get('/library/books/open-book/file')->assertOk()->assertHeader('Content-Type', 'application/pdf')->streamedContent())->toBe(pdfContents())
        ->and($this->get('/library/books/open-book/download')->assertOk()->headers->get('Content-Disposition'))->toStartWith('attachment');

    $this->get('/governance/open-doc')->assertOk()->assertDontSee('data-pdf-ticket', false);
    $this->get('/governance/open-doc/file')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get('/governance/open-doc/download')->assertOk();

    $this->get('/library/audio/talk/file')->assertOk();
});
