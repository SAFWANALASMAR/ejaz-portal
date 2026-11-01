<?php

use App\Actions\Governance\SaveDocument;
use App\Enums\DocumentCategory;
use App\Enums\RoleName;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Models\Document;
use App\Support\ReaderFileResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| وثائق الحوكمة «للاطلاع فقط» (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٢٢ — docs/decisions/2026-11-01-director-notes.md):
| قارئ المكتبة نفسه، بلا تنزيل ولا فتح للملف في عارض المتصفح، وخيار لكل وثيقة افتراضه للاطلاع فقط. يصعّب الحفظ ولا يمنعه
| منعاً تاماً.
*/

beforeEach(function () {
    Storage::fake(Document::disk());
});

function governanceFile(array $attributes = []): Document
{
    $document = Document::factory()->create($attributes);

    if ($document->file !== null) {
        Storage::disk(Document::disk())->put($document->file, pdfContents());
    }

    return $document;
}

it('adds the option with a default of view only, and the migration leaves existing documents view only', function () {
    expect(Schema::hasColumn('documents', 'is_view_only'))->toBeTrue();

    $id = DB::table('documents')->insertGetId(['slug' => 'raw', 'title' => 'وثيقة', 'category' => DocumentCategory::Policies->value, 'file' => 'documents/raw.pdf', 'is_published' => true, 'created_at' => now(), 'updated_at' => now()]);

    expect(Document::find($id)->is_view_only)->toBeTrue()
        ->and(Document::find($id)->isViewOnly())->toBeTrue();
});

it('shows a view-only document in the read-only reader with no download, open or print link', function () {
    $document = governanceFile(['slug' => 'annual-report'])->forceFill(['is_view_only' => true]);
    $document->save();

    $html = $this->get('/governance/annual-report')->assertOk()->getContent();

    expect($html)->toContain('data-pdf-reader="'.route('governance.file', 'annual-report').'"')
        ->toContain('data-view-only-badge')
        ->toContain(__('governance.view_only.reader.label', ['title' => $document->title]))
        ->not->toContain(route('governance.download', 'annual-report'))
        ->not->toContain('<iframe');

    // ولا زر تنزيل في بطاقتها في القائمة
    expect($this->get('/governance')->assertOk()->getContent())->not->toContain(route('governance.download', 'annual-report'));
});

it('answers 404 for the download of a view-only document, to guests and signed-in users alike', function () {
    governanceFile(['slug' => 'policy', 'is_view_only' => true]);

    $this->get('/governance/policy/download')->assertNotFound();
    $this->actingAs(userWithRole(RoleName::Member))->get('/governance/policy/download')->assertNotFound();
});

it('serves a view-only file only inline to the reader, without caching and without an attachment', function () {
    governanceFile(['slug' => 'policy', 'is_view_only' => true]);

    $response = $this->get('/governance/policy/file')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and((string) $response->headers->get('Content-Disposition'))->toStartWith('inline')->not->toContain('attachment')
        ->and($response->headers->get('X-Robots-Tag'))->toContain('noindex')
        // السياسة الكاملة بلا سطر التضمين، فلا يُضمَّن في إطار
        ->and((string) $response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});

it('sends a direct visit to the view-only file (tab or frame) back to the document page, away from the browser viewer', function (string $destination) {
    governanceFile(['slug' => 'policy', 'is_view_only' => true]);

    $this->withHeaders(['Sec-Fetch-Dest' => $destination])->get('/governance/policy/file')->assertRedirect(route('governance.show', 'policy'));
})->with(['document', 'iframe', 'embed', 'object']);

it('limits the reader file per address like the library reader', function () {
    governanceFile(['slug' => 'policy', 'is_view_only' => true]);

    for ($i = 0; $i < ReaderFileResponse::PER_MINUTE; $i++) {
        $this->get('/governance/policy/file')->assertOk();
    }

    $this->get('/governance/policy/file')->assertStatus(429);
});

it('keeps unpublished documents unreachable in every way', function () {
    governanceFile(['slug' => 'draft', 'is_view_only' => true, 'is_published' => false]);

    $this->get('/governance/draft')->assertNotFound();
    $this->get('/governance/draft/file')->assertNotFound();
    $this->get('/governance/draft/download')->assertNotFound();
});

it('keeps download and the embedded viewer when the option is off', function () {
    governanceFile(['slug' => 'open-doc', 'is_view_only' => false]);

    $html = $this->get('/governance/open-doc')->assertOk()->getContent();
    expect($html)->toContain(route('governance.download', 'open-doc'))->toContain('<iframe')->not->toContain('data-pdf-reader');

    $this->get('/governance/open-doc/download')->assertOk()->assertDownload('open-doc.pdf');
});

it('leaves a direct-link document with its link, whatever the option', function () {
    Document::factory()->link('https://example.org/report')->create(['slug' => 'linked', 'is_view_only' => true]);

    expect(Document::where('slug', 'linked')->sole()->isViewOnly())->toBeFalse();
    $this->get('/governance/linked')->assertOk()->assertSee('https://example.org/report', false)->assertDontSee('data-pdf-reader', false);
    $this->get('/governance/linked/file')->assertNotFound();
});

it('lets an editor turn the option off and on from the admin, defaulting new documents to view only, and logs the change', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    $this->actingAs($editor);

    Livewire::test(CreateDocument::class)
        ->assertFormFieldExists('is_view_only')
        ->assertSchemaStateSet(['is_view_only' => true])
        ->fillForm([
            'title' => 'لائحة', 'slug' => 'bylaw', 'category' => DocumentCategory::Policies->value,
            'file' => [UploadedFile::fake()->createWithContent('bylaw.pdf', pdfContents())], 'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $document = Document::where('slug', 'bylaw')->sole();
    expect($document->is_view_only)->toBeTrue();

    Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
        ->fillForm(['is_view_only' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($document->refresh()->is_view_only)->toBeFalse()
        ->and(Activity::whereMorphedTo('subject', $document)->where('event', 'updated')->latest('id')->first()->attribute_changes['attributes']['is_view_only'])->toBeFalse();
});

it('refuses the option change to anyone without the documents update permission, and a non-boolean value', function () {
    $document = governanceFile(['is_view_only' => true]);

    expect(fn () => app(SaveDocument::class)->handle(userWithRole(RoleName::Member), $document, ['is_view_only' => false]))
        ->toThrow(AuthorizationException::class);
    expect($document->refresh()->is_view_only)->toBeTrue();

    expect(fn () => app(SaveDocument::class)->handle(userWithRole(RoleName::ContentEditor), $document, ['is_view_only' => 'perhaps']))
        ->toThrow(ValidationException::class);
});
