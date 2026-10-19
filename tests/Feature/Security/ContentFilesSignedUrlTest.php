<?php

use App\Models\Document;
use App\Models\LibraryItem;
use App\Support\SpaLink;
use Illuminate\Support\Facades\Storage;

/*
| ملفات الحوكمة والمكتبة على S3 (roadmap.md، خطة I8): بعد فحص النشر تحويل 302 إلى رابط تخزين موقّع قصير، لا بث
| عبر PHP. والتحويل لا يُخزَّن عند وسيط مشترك، ولا يدخل الرابط صفحة مخزنة، ولا رابط لملف غير منشور.
| والقرص المحلي في التطوير يبقى بثاً من التطبيق (GovernanceTest وLibraryTest).
*/

beforeEach(function () {
    config(['filesystems.disks.content-s3' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/content-s3')]]);
    $this->disk = Storage::fake('content-s3');
    config(['filesystems.disks.content-s3.driver' => 's3', 'filesystems.private_disk' => 'content-s3', 'filesystems.content_url_minutes' => 60]);

    $this->signed = $signed = new ArrayObject;
    $this->disk->buildTemporaryUrlsUsing(function (string $path, DateTimeInterface $expiration, array $options) use ($signed) {
        $signed[] = ['path' => $path, 'expiration' => $expiration, 'options' => $options];

        return 'https://storage.example.test/content/'.$path.'?signature=x';
    });
});

function s3Document(array $attributes = []): Document
{
    $document = Document::factory()->create($attributes);
    Storage::disk('content-s3')->put($document->file, pdfContents());

    return $document;
}

function s3LibraryItem(array $attributes = [], bool $audio = false): LibraryItem
{
    $factory = LibraryItem::factory();
    $item = ($audio ? $factory->audio() : $factory)->create($attributes);
    Storage::disk('content-s3')->put((string) $item->file, $audio ? mp3Contents() : pdfContents());

    return $item;
}

it('redirects a published governance file to a short signed link, inline for the viewer and as a download', function () {
    $document = s3Document(['slug' => 'annual-report']);

    $inline = $this->get('/governance/annual-report/file')->assertRedirect('https://storage.example.test/content/'.$document->file.'?signature=x');
    $download = $this->get('/governance/annual-report/download')->assertStatus(302);

    expect($this->signed->getArrayCopy())->toHaveCount(2)
        ->and($this->signed[0]['options']['ResponseContentType'])->toBe('application/pdf')
        ->and($this->signed[0]['options']['ResponseContentDisposition'])->toStartWith('inline')->toContain('annual-report.pdf')
        ->and($this->signed[1]['options']['ResponseContentDisposition'])->toStartWith('attachment')
        ->and($this->signed[0]['options']['ResponseCacheControl'])->toBe('private, no-store')
        ->and(now()->addMinutes(60)->getTimestamp() - $this->signed[0]['expiration']->getTimestamp())->toBeBetween(0, 5);

    // التحويل لا يحتفظ به وسيط مشترك، فلا يبقى بعد إلغاء النشر ولا يصل غير طالبه
    foreach ([$inline, $download] as $response) {
        expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')->not->toContain('public');
    }
});

it('signs no link for an unpublished, deleted, unknown or missing governance file', function () {
    s3Document(['slug' => 'draft', 'is_published' => false]);
    s3Document(['slug' => 'removed'])->delete();
    Document::factory()->create(['slug' => 'no-file']);

    foreach (['draft', 'removed', 'unknown', 'no-file'] as $slug) {
        $this->get('/governance/'.$slug.'/file')->assertNotFound();
        $this->get('/governance/'.$slug.'/download')->assertNotFound();
    }

    expect($this->signed->getArrayCopy())->toBe([]);
});

it('stops signing as soon as the document is unpublished', function () {
    $document = s3Document(['slug' => 'report']);
    $this->get('/governance/report/file')->assertStatus(302);

    $document->update(['is_published' => false]);

    $this->get('/governance/report/file')->assertNotFound();
    expect($this->signed->getArrayCopy())->toHaveCount(1);
});

it('redirects a published library book and audio file to a signed link with its type', function () {
    $book = s3LibraryItem(['slug' => 'book']);
    $lecture = s3LibraryItem(['slug' => 'lecture'], audio: true);

    $this->get('/library/books/book/file')->assertRedirect('https://storage.example.test/content/'.$book->file.'?signature=x');
    $this->get('/library/audio/lecture/file')->assertRedirect('https://storage.example.test/content/'.$lecture->file.'?signature=x');
    $this->get('/library/audio/lecture/download')->assertStatus(302)
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(array_map(fn (array $signed): string => $signed['options']['ResponseContentType'], $this->signed->getArrayCopy()))->toBe(['application/pdf', 'audio/mpeg', 'audio/mpeg'])
        ->and($this->signed[1]['options']['ResponseContentDisposition'])->toStartWith('inline')
        ->and($this->signed[2]['options']['ResponseContentDisposition'])->toStartWith('attachment');
});

it('signs no link for a library file that is unpublished, deleted, missing or under another section', function () {
    s3LibraryItem(['slug' => 'draft', 'is_published' => false]);
    s3LibraryItem(['slug' => 'removed'])->delete();
    LibraryItem::factory()->create(['slug' => 'no-file']);
    s3LibraryItem(['slug' => 'book']);

    foreach (['books/draft', 'books/removed', 'books/no-file', 'audio/book', 'books/unknown'] as $path) {
        $this->get('/library/'.$path.'/file')->assertNotFound();
        $this->get('/library/'.$path.'/download')->assertNotFound();
    }

    expect($this->signed->getArrayCopy())->toBe([]);
});

it('keeps the signed link out of the cached pages, which point to the checked route', function () {
    s3Document(['slug' => 'report']);
    s3LibraryItem(['slug' => 'book']);

    $this->get('/governance/report')->assertOk()
        ->assertSee('<iframe src="'.route('governance.file', 'report').'"', false)
        ->assertDontSee('storage.example.test');
    $this->get('/library/books/book')->assertOk()
        ->assertSee('<iframe src="'.route('library.file', ['books', 'book']).'"', false)
        ->assertDontSee('storage.example.test');

    expect($this->signed->getArrayCopy())->toBe([]);
});

it('allows framing the storage origin from CSP_FRAME_SRC alone, ignoring a wildcard or a scheme', function () {
    config(['security.csp.storage_frame_src' => ['https://eu2.contabostorage.com/', '*', 'https:', 'http://plain.example.test']]);

    s3Document(['slug' => 'report']);
    $frameSrc = fn (string $url): array => explode(' ', str($this->get($url)->headers->get('Content-Security-Policy'))->after('frame-src ')->before(';')->toString());

    expect($frameSrc('/governance/report'))->toContain("'self'")->toContain('https://eu2.contabostorage.com')
        ->not->toContain('*')->not->toContain('https:')->not->toContain('http://plain.example.test');

    // وفي الصفحة التي يُتنقّل منها إلى الوثيقة بسرعة: التنقّل يُبقي سياستها، فبدونه يُمنع العارض
    expect($frameSrc('/governance'))->toContain('https://eu2.contabostorage.com')
        ->and(SpaLink::allows(route('governance.show', 'report')))->toBeTrue();
});
