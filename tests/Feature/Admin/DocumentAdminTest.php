<?php

use App\Actions\Governance\SaveDocument;
use App\Enums\DocumentCategory;
use App\Enums\RoleName;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Models\Document;
use App\Rules\RealPdf;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
    $this->editor = userWithRole(RoleName::ContentEditor);
});

/**
 * @return array<string, mixed>
 */
function documentForm(UploadedFile $file, array $overrides = []): array
{
    return [
        'title' => 'سياسة الخصوصية',
        'slug' => 'privacy-policy',
        'category' => DocumentCategory::Policies->value,
        'issued_at' => '2026-01-15',
        'version' => '1',
        'file' => [$file],
        'is_published' => true,
        ...$overrides,
    ];
}

it('opens the documents screen to the editor, lets the board look only, and keeps others out', function () {
    $this->actingAs($this->editor)->get(DocumentResource::getUrl('index'))->assertOk();
    $this->actingAs($this->editor)->get(DocumentResource::getUrl('create'))->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(DocumentResource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(DocumentResource::getUrl('create'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Secretary))->get(DocumentResource::getUrl('index'))->assertForbidden();
});

it('creates a document with a real PDF in the private disk, logs it and publishes it', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateDocument::class)
        ->fillForm(documentForm(UploadedFile::fake()->createWithContent('policy.pdf', pdfContents())))
        ->call('create')
        ->assertHasNoFormErrors();

    $document = Document::where('slug', 'privacy-policy')->sole();

    expect($document->file)->toStartWith('documents/')
        ->and(Storage::disk('local')->exists($document->file))->toBeTrue()
        ->and($document->file_size)->toBeGreaterThan(0)
        ->and(Activity::whereMorphedTo('subject', $document)->where('event', 'created')->exists())->toBeTrue();

    $this->get('/governance/privacy-policy/file')->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('refuses a file that is not a real PDF or that carries active content', function (Closure $file) {
    $this->actingAs($this->editor);

    Livewire::test(CreateDocument::class)
        ->fillForm(documentForm($file()))
        ->call('create')
        ->assertHasFormErrors(['file']);

    expect(Document::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('documents'))->toBe([]);
})->with([
    'text renamed to pdf' => [fn () => UploadedFile::fake()->createWithContent('fake.pdf', 'نص عادي وليس PDF')],
    'script' => [fn () => UploadedFile::fake()->createWithContent('js.pdf', pdfContents('4 0 obj << /S /JavaScript /JS (app.alert(1)) >> endobj'))],
    'hex-encoded script' => [fn () => UploadedFile::fake()->createWithContent('hex.pdf', pdfContents('4 0 obj << /S /J#61vaScript >> endobj'))],
    'launch action' => [fn () => UploadedFile::fake()->createWithContent('launch.pdf', pdfContents('4 0 obj << /S /Launch /F (cmd.exe) >> endobj'))],
    'embedded file' => [fn () => UploadedFile::fake()->createWithContent('embed.pdf', pdfContents('4 0 obj << /Type /EmbeddedFile >> endobj'))],
    'too large' => [fn () => UploadedFile::fake()->create('big.pdf', 21000, 'application/pdf')],
]);

it('checks the stored file again when the action is called directly, and only inside its folder', function () {
    Storage::disk('local')->put('documents/fake.pdf', 'نص عادي');
    Storage::disk('local')->put('other/keep.pdf', pdfContents());

    expect(fn () => app(SaveDocument::class)->handle($this->editor, new Document, documentForm(UploadedFile::fake()->create('x.pdf'), ['file' => 'documents/fake.pdf'])))
        ->toThrow(ValidationException::class)
        ->and(Storage::disk('local')->exists('documents/fake.pdf'))->toBeFalse()
        ->and(fn () => app(SaveDocument::class)->handle($this->editor, new Document, documentForm(UploadedFile::fake()->create('x.pdf'), ['file' => 'other/keep.pdf'])))
        ->toThrow(ValidationException::class)
        ->and(Storage::disk('local')->exists('other/keep.pdf'))->toBeTrue()
        ->and(Document::count())->toBe(0);
});

it('removes the previous file when the file is replaced', function () {
    Storage::disk('local')->put('documents/old.pdf', pdfContents());
    Storage::disk('local')->put('documents/new.pdf', pdfContents());
    $document = Document::factory()->create(['file' => 'documents/old.pdf']);

    app(SaveDocument::class)->handle($this->editor, $document, ['file' => 'documents/new.pdf']);

    expect($document->fresh()->file)->toBe('documents/new.pdf')
        ->and(Storage::disk('local')->exists('documents/old.pdf'))->toBeFalse();
});

it('replaces the file from the edit screen and removes the old one', function () {
    Storage::disk('local')->put('documents/old.pdf', pdfContents());
    $document = Document::factory()->create(['file' => 'documents/old.pdf']);
    $this->actingAs($this->editor);

    Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
        ->fillForm(['file' => [UploadedFile::fake()->createWithContent('new.pdf', pdfContents())]])
        ->call('save')
        ->assertHasNoFormErrors();

    $file = $document->fresh()->file;

    expect($file)->not->toBe('documents/old.pdf')->toStartWith('documents/')
        ->and(Storage::disk('local')->allFiles('documents'))->toBe([$file]);
});

it('keeps the document and its file when the edit screen refuses a replacement', function () {
    Storage::disk('local')->put('documents/old.pdf', pdfContents());
    $document = Document::factory()->create(['file' => 'documents/old.pdf', 'title' => 'العنوان المحفوظ']);
    $this->actingAs($this->editor);

    Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
        ->fillForm([
            'title' => 'عنوان لم يُحفظ',
            'file' => [UploadedFile::fake()->createWithContent('bad.pdf', pdfContents('4 0 obj << /S /JavaScript /JS (app.alert(1)) >> endobj'))],
        ])
        ->call('save')
        ->assertHasFormErrors(['file']);

    expect($document->fresh())->file->toBe('documents/old.pdf')->title->toBe('العنوان المحفوظ')
        ->and(Storage::disk('local')->allFiles('documents'))->toBe(['documents/old.pdf']);
});

it('shows a refusal from the action under the file field, and puts the saved file back in it', function () {
    Storage::disk('local')->put('documents/old.pdf', pdfContents());
    $document = Document::factory()->create(['file' => 'documents/old.pdf']);
    $this->actingAs($this->editor);

    // رفض لا يصل إليه فحص النموذج، كملف تملكه وثيقة أخرى
    $this->mock(SaveDocument::class)->shouldReceive('handle')->once()
        ->andThrow(ValidationException::withMessages(['file' => __('validation.file_in_use')]));

    Livewire::test(EditDocument::class, ['record' => $document->getRouteKey()])
        ->fillForm(['file' => [UploadedFile::fake()->createWithContent('new.pdf', pdfContents())]])
        ->call('save')
        ->assertHasFormErrors(['file' => __('validation.file_in_use')])
        ->assertSet('data.file', fn (array $state): bool => array_values($state) === ['documents/old.pdf']);

    expect($document->fresh()->file)->toBe('documents/old.pdf');
});

it('lists the documents without a date last, and filters them by year and by publishing', function () {
    $dated = Document::factory()->create(['title' => 'مؤرخة', 'issued_at' => '2025-03-01']);
    $undated = Document::factory()->create(['title' => 'بلا تاريخ', 'issued_at' => null]);
    $draft = Document::factory()->unpublished()->create(['title' => 'مسودة', 'issued_at' => '2024-06-01']);
    $this->actingAs($this->editor);

    Livewire::test(ListDocuments::class)
        ->assertCanSeeTableRecords([$dated, $draft, $undated], inOrder: true)
        ->filterTable('year', 2025)
        ->assertCanSeeTableRecords([$dated])
        ->assertCanNotSeeTableRecords([$draft, $undated]);

    Livewire::test(ListDocuments::class)
        ->filterTable('is_published', false)
        ->assertCanSeeTableRecords([$draft])
        ->assertCanNotSeeTableRecords([$dated, $undated]);
});

it('soft deletes and restores a document, keeping its file, and never deletes it for good', function () {
    Storage::disk('local')->put('documents/kept.pdf', pdfContents());
    $document = Document::factory()->create(['file' => 'documents/kept.pdf', 'slug' => 'kept']);
    $this->actingAs($this->editor);

    Livewire::test(ListDocuments::class)->callAction(TestAction::make('delete')->table($document));

    expect($document->fresh()->trashed())->toBeTrue()
        ->and(Storage::disk('local')->exists('documents/kept.pdf'))->toBeTrue();
    $this->get('/governance/kept')->assertNotFound();

    Livewire::test(ListDocuments::class)->filterTable('trashed', true)->callAction(TestAction::make('restore')->table($document));

    expect($document->fresh()->trashed())->toBeFalse()
        ->and(userWithRole(RoleName::TechnicalAdmin)->can('forceDelete', $document))->toBeFalse();
    $this->get('/governance/kept')->assertOk();
});

it('refuses to save a document for someone without the permission, wherever it is called from', function () {
    Storage::disk('local')->put('documents/x.pdf', pdfContents());

    expect(fn () => app(SaveDocument::class)->handle(userWithRole(RoleName::BoardMember), new Document, documentForm(UploadedFile::fake()->create('x.pdf'), ['file' => 'documents/x.pdf'])))
        ->toThrow(AuthorizationException::class);

    expect(Document::count())->toBe(0);
});

it('finds active content hidden in a compressed stream, without refusing harmless compressed streams', function () {
    $stream = fn (string $content): string => "4 0 obj\n<< /Filter /FlateDecode >>\nstream\n".gzcompress($content)."\nendstream\nendobj";

    expect(RealPdf::problem(pdfContents($stream('<< /S /JavaScript /JS (app.alert(1)) >>'))))->toBe('validation.pdf_active_content')
        ->and(RealPdf::problem(pdfContents($stream('<< /XFA [] >>'))))->toBe('validation.pdf_active_content')
        ->and(RealPdf::problem(pdfContents($stream('BT /F1 12 Tf (نص الصفحة) Tj ET'))))->toBeNull()
        ->and(RealPdf::problem(pdfContents()))->toBeNull();
});

it('lets those who may view a document download its file from the panel, and nobody else', function () {
    Storage::disk('local')->put('documents/draft.pdf', pdfContents());
    $document = Document::factory()->unpublished()->create(['file' => 'documents/draft.pdf', 'slug' => 'draft']);

    foreach ([$this->editor, userWithRole(RoleName::BoardMember)] as $user) {
        $this->actingAs($user);

        Livewire::test(ListDocuments::class)
            ->callAction(TestAction::make('downloadFile')->table($document))
            ->assertFileDownloaded('draft.pdf');
    }

    $this->actingAs(userWithRole(RoleName::Secretary))->get(DocumentResource::getUrl('index'))->assertForbidden();
});

it('refuses the file of another document, even a deleted one', function () {
    Storage::disk('local')->put('documents/shared.pdf', pdfContents());
    Document::factory()->create(['file' => 'documents/shared.pdf'])->delete();

    expect(fn () => app(SaveDocument::class)->handle($this->editor, new Document, documentForm(UploadedFile::fake()->create('x.pdf'), ['file' => 'documents/shared.pdf'])))
        ->toThrow(ValidationException::class, __('validation.file_in_use'))
        ->and(Storage::disk('local')->exists('documents/shared.pdf'))->toBeTrue();
});

it('refuses a PDF larger than the reading cap without reading it', function () {
    $file = UploadedFile::fake()->createWithContent('big.pdf', pdfContents().str_repeat(' ', RealPdf::MAX_FILE_BYTES));
    $failed = false;

    (new RealPdf)->validate('file', $file, function () use (&$failed) {
        $failed = true;

        return new class
        {
            public function translate(): void {}
        };
    });

    expect($failed)->toBeTrue();
});
