<?php

use App\Enums\RoleName;
use App\Models\Document;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\Page;
use App\Models\Redirect;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake(Document::disk());
    Storage::fake('public');

    $this->dir = sys_get_temp_dir().'/legacy-import-'.Str::random(8);
    File::ensureDirectoryExists($this->dir.'/export/files');
    File::put($this->dir.'/export/files/license.pdf', pdfContents());
    File::put($this->dir.'/export/files/book.pdf', pdfContents());
    File::put($this->dir.'/export/files/talk.mp3', mp3Contents());
    File::put($this->dir.'/export/files/fake.pdf', 'not a pdf');
    File::put($this->dir.'/outside.pdf', pdfContents());

    // ملف الاستيراد فيه «عن الجمعية»، وهي في «محرر الصفحات» لمن له pages.edit_all وحده، فالمنفّذ مسؤول تقني كما في الإطلاق
    // (docs/decisions/2026-11-02-page-editor.md)
    $this->editor = userWithRole(RoleName::TechnicalAdmin);
});

afterEach(fn () => File::deleteDirectory($this->dir));

/**
 * ملف استيراد في مجلد التصدير، بقيم تجريبية.
 *
 * @param  array<string, mixed>  $data
 */
function writeImportFile(string $dir, array $data): string
{
    File::put($dir.'/export/import.json', json_encode($data, JSON_UNESCAPED_UNICODE));

    return $dir.'/export/import.json';
}

/**
 * @return array<string, mixed>
 */
function sampleImport(): array
{
    return [
        'pages' => [[
            'slug' => 'about',
            'title' => 'عن الجمعية',
            'summary' => 'ملخص تجريبي',
            'blocks' => [
                ['type' => 'text', 'data' => ['heading' => 'عنوان تجريبي', 'body' => '<p>نص تجريبي</p>']],
                ['type' => 'cards', 'data' => ['heading' => 'بطاقات', 'items' => [['icon' => 'star', 'title' => 'بطاقة تجريبية', 'text' => 'وصف']]]],
            ],
        ]],
        'initiatives' => [[
            'slug' => 'qiyam',
            'name' => 'قيم',
            'summary' => 'وصف تجريبي',
            'goals' => ['هدف تجريبي'],
            'legacy_paths' => ['/مبادرة-قيـم/'],
        ]],
        'documents' => [[
            'slug' => 'license',
            'title' => 'وثيقة تجريبية',
            'category' => 'official',
            'issued_at' => '2026-01-01',
            'file' => 'files/license.pdf',
            'legacy_paths' => ['https://ejaz.sa/wp-content/uploads/2026/01/'.rawurlencode('شهادة').'.pdf'],
        ]],
        'library' => [
            ['slug' => 'talk', 'section' => 'audio', 'title' => 'محاضرة تجريبية', 'file' => 'files/talk.mp3'],
            ['slug' => 'book', 'section' => 'books', 'title' => 'كتاب تجريبي', 'file' => 'files/book.pdf', 'tags' => ['تجريبي']],
            ['slug' => 'clip', 'section' => 'videos', 'title' => 'مقطع تجريبي'],
        ],
        'redirects' => [['from' => '/old-page/', 'to' => '/about']],
    ];
}

it('imports every kind as an unpublished draft through its own action, in the name of the actor', function () {
    $file = writeImportFile($this->dir, sampleImport());

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->editor->email])
        ->expectsOutputToContain(__('import.outcomes.created'))
        ->assertSuccessful();

    $page = Page::query()->where('slug', 'about')->sole();
    $document = Document::query()->where('slug', 'license')->sole();
    $talk = LibraryItem::query()->where('slug', 'talk')->sole();

    expect($page->is_published)->toBeFalse()
        ->and($page->blocks)->toHaveCount(2)
        ->and(Initiative::query()->where('slug', 'qiyam')->value('goals'))->toBe(['هدف تجريبي'])
        ->and($document->is_published)->toBeFalse()
        ->and($document->file)->toStartWith('documents/')->toEndWith('.pdf')
        ->and(Storage::disk(Document::disk())->get((string) $document->file))->toBe(pdfContents())
        ->and($document->file_size)->toBe(strlen(pdfContents()))
        ->and($talk->file)->toStartWith('library/')->toEndWith('.mp3')
        ->and(LibraryItem::query()->where('slug', 'book')->value('tags'))->toBe(['تجريبي'])
        ->and(LibraryItem::query()->where('slug', 'clip')->value('is_published'))->toBeFalse()
        ->and(LibraryItem::query()->where('is_published', true)->exists())->toBeFalse()
        ->and(Activity::whereMorphedTo('subject', $document)->where('event', 'created')->sole()->causer?->is($this->editor))->toBeTrue();

    // الروابط القديمة تُحوَّل إلى صفحات سجلاتها
    // بترتيب الإنشاء صراحة: بلا ORDER BY يعيد PostgreSQL الصفوف بأي ترتيب (الصف المعدَّل ينتقل إلى آخر الجدول)
    expect(Redirect::query()->orderBy('id')->pluck('target', 'source')->all())->toBe([
        'مبادرة-قيـم' => '/initiatives/qiyam',
        'wp-content/uploads/2026/01/شهادة.pdf' => '/governance/license',
        'old-page' => '/about',
    ]);
    $this->get('/wp-content/uploads/2026/01/'.rawurlencode('شهادة').'.pdf')->assertStatus(301)->assertRedirect('/governance/license');
});

it('fills only the blank fields of a draft, and leaves published and deleted records alone', function () {
    $this->seed(ContentSeeder::class);
    Page::query()->where('slug', 'about')->update(['summary' => 'ملخص المحرر']);
    Initiative::query()->where('slug', 'tatweer')->update(['summary' => 'نص المحرر', 'is_published' => true]);
    Document::factory()->create(['slug' => 'license'])->delete();

    $data = sampleImport();
    $data['initiatives'][] = ['slug' => 'tatweer', 'name' => 'تطوير', 'summary' => 'نص مستورد', 'legacy_paths' => ['/مبادرة-تطوير/']];
    $file = writeImportFile($this->dir, $data);

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->editor->email])
        ->expectsOutputToContain(__('import.outcomes.filled', ['fields' => 'blocks']))
        ->expectsOutputToContain(__('import.outcomes.published'))
        ->expectsOutputToContain(__('import.outcomes.deleted'))
        ->assertSuccessful();

    $about = Page::query()->where('slug', 'about')->sole();

    expect($about->summary)->toBe('ملخص المحرر')
        ->and($about->blocks)->toHaveCount(2)
        ->and($about->is_published)->toBeFalse()
        ->and(Initiative::query()->where('slug', 'qiyam')->value('summary'))->toBe('وصف تجريبي')
        ->and(Initiative::query()->where('slug', 'tatweer')->value('summary'))->toBe('نص المحرر')
        ->and(Initiative::query()->count())->toBe(5)
        ->and(Document::withTrashed()->where('slug', 'license')->sole()->trashed())->toBeTrue()
        ->and(Storage::disk(Document::disk())->allFiles('documents'))->toBeEmpty();

    // المنشور تُحوَّل إليه روابطه القديمة، والمحذوف لا
    expect(Redirect::query()->where('source', 'مبادرة-تطوير')->value('target'))->toBe('/initiatives/tatweer')
        ->and(Redirect::query()->where('target', '/governance/license')->exists())->toBeFalse();
});

it('changes nothing and duplicates nothing when run again', function () {
    $file = writeImportFile($this->dir, sampleImport());
    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->editor->email])->assertSuccessful();
    $files = Storage::disk(Document::disk())->allFiles();
    $activities = Activity::count();

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->editor->email])
        ->expectsOutputToContain(__('import.outcomes.unchanged'))
        ->doesntExpectOutputToContain(__('import.outcomes.created'))
        ->assertSuccessful();

    expect(Page::count() + Initiative::count() + Document::count() + LibraryItem::count())->toBe(6)
        ->and(Redirect::count())->toBe(3)
        ->and(Storage::disk(Document::disk())->allFiles())->toBe($files)
        ->and(Activity::count())->toBe($activities);
});

it('rejects a broken record with its reason, imports the rest and fails the run', function () {
    // ملف خارج مجلد التصدير، مباشرة أو برابط رمزي داخله
    symlink($this->dir.'/outside.pdf', $this->dir.'/export/files/link.pdf');

    $file = writeImportFile($this->dir, [
        'pages' => [
            ['slug' => 'with-image', 'title' => 'صفحة تجريبية', 'blocks' => [['type' => 'image', 'data' => ['picture' => 'pages/x.jpg']]]],
            ['slug' => 'no-body', 'title' => 'صفحة تجريبية', 'blocks' => [['type' => 'text', 'data' => ['heading' => 'عنوان']]]],
        ],
        'documents' => [
            ['slug' => 'outside', 'title' => 'وثيقة', 'category' => 'official', 'file' => '../outside.pdf'],
            ['slug' => 'linked', 'title' => 'وثيقة', 'category' => 'official', 'file' => 'files/link.pdf'],
            ['slug' => 'missing', 'title' => 'وثيقة', 'category' => 'official', 'file' => 'files/none.pdf'],
            ['slug' => 'fake', 'title' => 'وثيقة', 'category' => 'official', 'file' => 'files/fake.pdf'],
            ['slug' => 'Bad Slug', 'title' => 'وثيقة', 'category' => 'official', 'file' => 'files/license.pdf'],
            ['slug' => 'secret', 'title' => 'وثيقة', 'category' => 'secret', 'file' => 'files/license.pdf'],
            ['slug' => 'control', 'title' => "وثيقة\u{202E}", 'category' => 'official', 'file' => 'files/license.pdf'],
            ['slug' => 'good', 'title' => 'وثيقة تجريبية', 'category' => 'official', 'file' => 'files/license.pdf'],
        ],
        'library' => [['slug' => 'clip', 'section' => 'videos', 'title' => 'مقطع', 'file' => 'files/talk.mp3']],
        'redirects' => [['from' => '/old-x', 'to' => 'https://evil.example'], ['from' => '/', 'to' => '/about']],
    ]);

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->editor->email])
        ->expectsOutputToContain(__('import.errors.file_missing', ['file' => '../outside.pdf']))
        ->expectsOutputToContain(__('import.errors.file_missing', ['file' => 'files/link.pdf']))
        ->expectsOutputToContain(__('import.errors.file_missing', ['file' => 'files/none.pdf']))
        ->expectsOutputToContain(__('validation.real_pdf'))
        ->expectsOutputToContain(__('import.errors.video_file'))
        ->expectsOutputToContain(__('redirects.errors.target'))
        ->expectsOutputToContain(__('redirects.errors.home'))
        ->assertFailed();

    expect(Page::count())->toBe(0)
        ->and(Document::query()->pluck('slug')->all())->toBe(['good'])
        ->and(Storage::disk(Document::disk())->allFiles())->toHaveCount(1)
        ->and(LibraryItem::count())->toBe(0)
        ->and(Redirect::count())->toBe(0);
});

it('keeps nothing in a dry run, yet reports what would happen', function () {
    $file = writeImportFile($this->dir, sampleImport());
    $activities = Activity::count();

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->editor->email, '--dry-run' => true])
        ->expectsOutputToContain(__('import.outcomes.created'))
        ->expectsOutputToContain(__('import.outcomes.redirect_created', ['from' => '/old-page/', 'to' => '/about']))
        ->expectsOutputToContain(__('import.dry_run_done'))
        ->assertSuccessful();

    expect(Page::count() + Initiative::count() + Document::count() + LibraryItem::count() + Redirect::count())->toBe(0)
        ->and(Storage::disk(Document::disk())->allFiles())->toBeEmpty()
        ->and(Activity::count())->toBe($activities);
});

it('runs only for an active account allowed to create what the file holds', function (string $who, string $error) {
    $file = writeImportFile($this->dir, sampleImport());
    $actor = match ($who) {
        'nobody' => null,
        'unknown' => 'nobody@example.com',
        'inactive' => tap(userWithRole(RoleName::ContentEditor), fn ($user) => $user->forceFill(['is_active' => false])->save())->email,
        default => userWithRole(RoleName::from($who))->email,
    };

    $this->artisan('import:legacy-site', array_filter(['file' => $file, '--actor' => $actor]))
        ->expectsOutputToContain(__($error))
        ->assertFailed();

    expect(Page::count() + Document::count() + Redirect::count())->toBe(0);
})->with([
    'no actor' => ['nobody', 'import.errors.actor'],
    'an unknown email' => ['unknown', 'import.errors.actor'],
    'an inactive editor' => ['inactive', 'import.errors.actor'],
    'a secretary' => [RoleName::Secretary->value, 'import.sections.pages'],
    'a board member' => [RoleName::BoardMember->value, 'import.sections.documents'],
    // محرر المحتوى يستورد كل شيء إلا «عن الجمعية»، فيتوقف قبل أي حفظ لا في منتصفه
    'a content editor' => [RoleName::ContentEditor->value, 'import.sections.pages'],
]);

it('refuses a file that is not JSON or not in the expected shape', function (string $contents, string $error) {
    File::put($this->dir.'/export/import.json', $contents);

    $this->artisan('import:legacy-site', ['file' => $this->dir.'/export/import.json', '--actor' => $this->editor->email])
        ->expectsOutputToContain($error === 'file' ? __('import.errors.file') : $error)
        ->assertFailed();
})->with([
    'not JSON' => ['{pages: ', 'file'],
    'an unknown section' => ['{"news": []}', 'news'],
    'a section that is not a list' => ['{"pages": {"slug": "about"}}', 'pages'],
]);

it('refuses a file that does not exist', function () {
    $this->artisan('import:legacy-site', ['file' => $this->dir.'/none.json', '--actor' => $this->editor->email])
        ->expectsOutputToContain(__('import.errors.file'))
        ->assertFailed();
});

it('prints nothing from the file that could tamper with the terminal', function () {
    $file = writeImportFile($this->dir, [
        'documents' => [['slug' => "x\e[2J", 'title' => 'وثيقة', 'category' => 'official', 'file' => 'files/license.pdf']],
        'redirects' => [['from' => "/old\e]0;title\x07", 'to' => '/about']],
    ]);

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->editor->email])
        ->expectsOutputToContain('#1')
        ->doesntExpectOutputToContain("\e[2J")
        ->doesntExpectOutputToContain("\e]0;")
        ->assertFailed();
});

it('keeps the direct link an editor gave a draft document, without copying the archive file to it', function () {
    Document::factory()->link('https://example.org/license')->unpublished()->create(['slug' => 'license', 'title' => 'وثيقة تجريبية']);
    $file = writeImportFile($this->dir, sampleImport());

    $this->artisan('import:legacy-site', ['file' => $file, '--actor' => $this->editor->email])->assertSuccessful();

    expect(Document::query()->where('slug', 'license')->sole())->file->toBeNull()->url->toBe('https://example.org/license')
        ->and(Storage::disk(Document::disk())->allFiles('documents'))->toBeEmpty();
});
