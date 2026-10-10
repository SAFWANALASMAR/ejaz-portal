<?php

use App\Actions\Documents\SaveArchiveCategory;
use App\Actions\Documents\SaveArchiveItem;
use App\Enums\RoleName;
use App\Models\ArchiveCategory;
use App\Models\ArchiveItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
| الأرشيف الشامل والشواهد (requirements/05): الوثائق والمراسلات برقم قيد تسلسلي بلا تكرار والشواهد،
| بتصنيفات لها صلاحيات اطلاع، وبحث وتصفية.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->secretary = userWithRole(RoleName::Secretary);
    $this->category = ArchiveCategory::factory()->create();
});

function archiveData(ArchiveCategory $category, array $overrides = []): array
{
    return [
        'type' => 'incoming',
        'archive_category_id' => $category->id,
        'title' => 'خطاب تجريبي',
        'entity' => 'جهة تجريبية',
        'dated_on' => '2026-03-10',
        'files' => [],
        ...$overrides,
    ];
}

function archiveFile(string $contents, string $extension = 'pdf'): array
{
    $path = ArchiveItem::DIRECTORY.'/'.fake()->uuid().'.'.$extension;
    Storage::disk('local')->put($path, $contents);

    return ['path' => $path, 'name' => 'ملف.'.$extension];
}

it('lets the super admin alone set categories and who sees them', function () {
    $data = ['name' => 'المراسلات السرية', 'roles' => ['secretary']];

    expect(fn () => app(SaveArchiveCategory::class)->handle($this->secretary, new ArchiveCategory, $data))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveArchiveCategory::class)->handle(userWithRole(RoleName::SuperAdmin), new ArchiveCategory, ['name' => 'تصنيف', 'roles' => ['member']]))->toThrow(ValidationException::class);

    expect(app(SaveArchiveCategory::class)->handle(userWithRole(RoleName::SuperAdmin), new ArchiveCategory, $data)->roles)->toBe(['secretary']);
});

it('numbers correspondence in sequence per type and year, and documents not at all', function () {
    $first = app(SaveArchiveItem::class)->handle($this->secretary, new ArchiveItem, archiveData($this->category));
    $second = app(SaveArchiveItem::class)->handle($this->secretary, new ArchiveItem, archiveData($this->category));
    $outgoing = app(SaveArchiveItem::class)->handle($this->secretary, new ArchiveItem, archiveData($this->category, ['type' => 'outgoing']));
    $nextYear = app(SaveArchiveItem::class)->handle($this->secretary, new ArchiveItem, archiveData($this->category, ['dated_on' => '2027-01-02']));
    $document = app(SaveArchiveItem::class)->handle($this->secretary, new ArchiveItem, archiveData($this->category, ['type' => 'document']));

    expect([$first->registration(), $second->registration(), $outgoing->registration(), $nextYear->registration(), $document->registration()])
        ->toBe(['2026/0001', '2026/0002', '2026/0001', '2027/0001', null]);

    // ولا يتكرر رقم في النوع والسنة ولو أُدخل من غير الإجراء
    expect(fn () => DB::table('archive_items')->insert([
        'uuid' => fake()->uuid(), 'type' => 'incoming', 'archive_category_id' => $this->category->id, 'title' => 'مكرر',
        'dated_on' => '2026-05-01', 'year' => 2026, 'registration_number' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('keeps the type and year of a numbered letter after saving', function () {
    $letter = app(SaveArchiveItem::class)->handle($this->secretary, new ArchiveItem, archiveData($this->category));

    expect(fn () => app(SaveArchiveItem::class)->handle($this->secretary, $letter, archiveData($this->category, ['dated_on' => '2027-02-01'])))->toThrow(ValidationException::class);

    app(SaveArchiveItem::class)->handle($this->secretary, $letter, archiveData($this->category, ['type' => 'document', 'title' => 'عنوان معدل']));
    expect($letter->fresh()->type->value)->toBe('incoming')
        ->and($letter->fresh()->registration())->toBe('2026/0001')
        ->and($letter->fresh()->title)->toBe('عنوان معدل');
});

it('shows items only to roles their category allows, and keeps others from filing into it', function () {
    $secret = ArchiveCategory::factory()->roles(['secretary'])->create();
    $item = ArchiveItem::factory()->create(['archive_category_id' => $secret->id]);
    $board = userWithRole(RoleName::BoardMember);

    expect($this->secretary->can('view', $item))->toBeTrue()
        ->and($board->can('view', $item))->toBeFalse()
        ->and($board->can('viewAny', ArchiveItem::class))->toBeTrue()
        ->and(ArchiveCategory::visibleTo($board)->pluck('id')->all())->toBe([$this->category->id])
        ->and(userWithRole(RoleName::SuperAdmin)->can('view', $item))->toBeTrue()
        ->and(userWithRole(RoleName::ContentEditor)->can('viewAny', ArchiveItem::class))->toBeFalse();

    $boardOnly = ArchiveCategory::factory()->roles(['board_member'])->create();
    expect(fn () => app(SaveArchiveItem::class)->handle($this->secretary, new ArchiveItem, archiveData($boardOnly)))->toThrow(AuthorizationException::class);
});

it('takes real PDFs and re-encoded images, and refuses other files', function () {
    $image = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($image);
    $jpeg = archiveFile((string) ob_get_clean(), 'jpg');

    $item = app(SaveArchiveItem::class)->handle($this->secretary, new ArchiveItem, archiveData($this->category, ['type' => 'evidence', 'files' => [archiveFile(pdfContents()), $jpeg]]));
    expect($item->files)->toHaveCount(2);

    $script = archiveFile('<?php echo 1;', 'php');
    expect(fn () => app(SaveArchiveItem::class)->handle($this->secretary, new ArchiveItem, archiveData($this->category, ['files' => [$script]])))->toThrow(ValidationException::class)
        ->and(Storage::disk('local')->exists($script['path']))->toBeFalse()
        // PDF بمحتوى نشط يرفضه RealPdf
        ->and(fn () => app(SaveArchiveItem::class)->handle($this->secretary, new ArchiveItem, archiveData($this->category, ['files' => [archiveFile(pdfContents('4 0 obj << /S /JavaScript /JS (app.alert(1)) >> endobj'))]])))->toThrow(ValidationException::class)
        // ملف عنصر آخر لا يُعاد استعماله
        ->and(fn () => app(SaveArchiveItem::class)->handle($this->secretary, new ArchiveItem, archiveData($this->category, ['files' => [['path' => $jpeg['path'], 'name' => 'x.jpg']]])))->toThrow(ValidationException::class);
});

it('keeps the board to reading the archive', function () {
    $board = userWithRole(RoleName::BoardMember);

    expect(fn () => app(SaveArchiveItem::class)->handle($board, new ArchiveItem, archiveData($this->category)))->toThrow(AuthorizationException::class)
        ->and($board->can('update', ArchiveItem::factory()->create(['archive_category_id' => $this->category->id])))->toBeFalse();
});
