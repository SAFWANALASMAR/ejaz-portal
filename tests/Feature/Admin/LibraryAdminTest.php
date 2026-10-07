<?php

use App\Actions\Library\SaveLibraryItem;
use App\Enums\LibrarySection;
use App\Enums\RoleName;
use App\Filament\Resources\LibraryItems\LibraryItemResource;
use App\Filament\Resources\LibraryItems\Pages\CreateLibraryItem;
use App\Filament\Resources\LibraryItems\Pages\EditLibraryItem;
use App\Filament\Resources\LibraryItems\Pages\ListLibraryItems;
use App\Models\LibraryItem;
use App\Rules\VideoUrl;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->editor = userWithRole(RoleName::ContentEditor);
});

/**
 * مقطع منشور برابط يوتيوب، وما يُغيَّر منه.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function libraryForm(array $overrides = []): array
{
    return [
        'title' => 'مراحل خلق الجنين',
        'slug' => 'embryo-stages',
        'section' => LibrarySection::Videos->value,
        'author' => 'متحدث تجريبي',
        'published_on' => '2026-02-01',
        'tags' => ['الأجنة'],
        'video_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
        'is_published' => true,
        ...$overrides,
    ];
}

it('opens the library screen to the editor, lets the board look only, and keeps others out', function () {
    $this->actingAs($this->editor)->get(LibraryItemResource::getUrl('index'))->assertOk();
    $this->actingAs($this->editor)->get(LibraryItemResource::getUrl('create'))->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(LibraryItemResource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(LibraryItemResource::getUrl('create'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Secretary))->get(LibraryItemResource::getUrl('index'))->assertForbidden();
});

it('creates a video item from a YouTube link, logs it and publishes it', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateLibraryItem::class)
        ->fillForm(libraryForm())
        ->call('create')
        ->assertHasNoFormErrors();

    $item = LibraryItem::where('slug', 'embryo-stages')->sole();

    expect($item->section)->toBe(LibrarySection::Videos)
        ->and($item->file)->toBeNull()
        ->and($item->tags)->toBe(['الأجنة'])
        ->and(Activity::whereMorphedTo('subject', $item)->where('event', 'created')->exists())->toBeTrue();

    $this->get('/library/videos/embryo-stages')->assertOk()->assertSee('youtube-nocookie.com/embed/abcdefghijk', false);
});

it('accepts YouTube links in their forms, and a video file from an approved host only', function (string $url, ?string $id, bool $valid) {
    config(['security.csp.media_src' => ['https://media.ejaz.sa']]);

    expect(VideoUrl::isValid($url))->toBe($valid)
        ->and(VideoUrl::youtubeId($url))->toBe($id);
})->with([
    'watch' => ['https://www.youtube.com/watch?v=abcdefghijk&t=30', 'abcdefghijk', true],
    'short link' => ['https://youtu.be/abcdefghijk?si=share', 'abcdefghijk', true],
    'embed' => ['https://www.youtube-nocookie.com/embed/abcdefghijk', 'abcdefghijk', true],
    'shorts' => ['https://youtube.com/shorts/abcdefghijk', 'abcdefghijk', true],
    'live' => ['https://m.youtube.com/live/abcdefghijk', 'abcdefghijk', true],
    'approved file' => ['https://media.ejaz.sa/videos/clip.mp4', null, true],
    'http' => ['http://www.youtube.com/watch?v=abcdefghijk', null, false],
    'user before host' => ['https://www.youtube.com@evil.example/watch?v=abcdefghijk', null, false],
    'look-alike host' => ['https://www.youtube.com.evil.example/watch?v=abcdefghijk', null, false],
    'other site' => ['https://vimeo.com/123456', null, false],
    'bad id' => ['https://www.youtube.com/watch?v=abc', null, false],
    'unapproved file host' => ['https://evil.example/clip.mp4', null, false],
    'page on approved host' => ['https://media.ejaz.sa/videos/clip.html', null, false],
    'script' => ['javascript:alert(1)', null, false],
]);

it('refuses a video link that is neither YouTube nor an approved file, under its field', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateLibraryItem::class)
        ->fillForm(libraryForm(['video_url' => 'https://evil.example/watch?v=abcdefghijk']))
        ->call('create')
        ->assertHasFormErrors(['video_url']);

    expect(LibraryItem::count())->toBe(0);
});

it('never publishes an item without its content, from the form or the action, but keeps it as a draft', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateLibraryItem::class)
        ->fillForm(libraryForm(['video_url' => null]))
        ->call('create')
        ->assertHasFormErrors(['video_url' => 'required']);

    expect(fn () => app(SaveLibraryItem::class)->handle($this->editor, new LibraryItem, libraryForm(['video_url' => null])))
        ->toThrow(ValidationException::class, __('library.validation.needs_source'));

    // مسودة بلا مصدر، فتُنقل العناوين قبل وصول روابطها
    app(SaveLibraryItem::class)->handle($this->editor, new LibraryItem, libraryForm(['video_url' => null, 'is_published' => false]));

    expect(LibraryItem::count())->toBe(1);
    $this->get('/library/videos/embryo-stages')->assertNotFound();
});

it('stores a real audio file in the private disk, and refuses one that is not audio', function () {
    $this->actingAs($this->editor);
    $audio = fn (UploadedFile $file): array => libraryForm(['section' => LibrarySection::Audio->value, 'file' => [$file]]);

    Livewire::test(CreateLibraryItem::class)
        ->fillForm($audio(UploadedFile::fake()->createWithContent('lecture.mp3', 'نص عادي وليس صوتاً')))
        ->call('create')
        ->assertHasFormErrors(['file']);

    Livewire::test(CreateLibraryItem::class)
        ->fillForm($audio(UploadedFile::fake()->createWithContent('lecture.mp3', mp3Contents())))
        ->call('create')
        ->assertHasNoFormErrors();

    $item = LibraryItem::sole();

    expect($item->file)->toStartWith('library/')->toEndWith('.mp3')
        ->and($item->video_url)->toBeNull()
        ->and($item->file_size)->toBe(strlen(mp3Contents()))
        ->and(Storage::disk('local')->allFiles('library'))->toBe([$item->file]);

    $this->get('/library/audio/embryo-stages/file')->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
});

it('accepts a real PDF for a study, and refuses one with active content', function () {
    $this->actingAs($this->editor);
    $study = fn (string $contents): array => libraryForm(['section' => LibrarySection::Research->value, 'file' => [UploadedFile::fake()->createWithContent('study.pdf', $contents)]]);

    Livewire::test(CreateLibraryItem::class)
        ->fillForm($study(pdfContents('4 0 obj << /S /JavaScript /JS (app.alert(1)) >> endobj')))
        ->call('create')
        ->assertHasFormErrors(['file']);

    Livewire::test(CreateLibraryItem::class)
        ->fillForm($study(pdfContents()))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LibraryItem::sole()->file)->toEndWith('.pdf');
    $this->get('/library/research/embryo-stages/file')->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('refuses a file that does not fit a new section, and clears the file when the item becomes a video', function () {
    Storage::disk('local')->put('library/lecture.mp3', mp3Contents());
    $item = LibraryItem::factory()->audio()->create(['file' => 'library/lecture.mp3']);

    // صوت صار كتاباً: الملف لا يناسبه، فيُرفض ويبقى للعنصر
    expect(fn () => app(SaveLibraryItem::class)->handle($this->editor, $item, ['section' => LibrarySection::Books->value]))
        ->toThrow(ValidationException::class, __('validation.real_pdf'))
        ->and(Storage::disk('local')->exists('library/lecture.mp3'))->toBeTrue()
        ->and($item->fresh()->section)->toBe(LibrarySection::Audio);

    // وصار مقطعاً: يُمسح الملف من العنصر ويُحذف بعد الحفظ
    app(SaveLibraryItem::class)->handle($this->editor, $item->fresh(), ['section' => LibrarySection::Videos->value, 'video_url' => 'https://youtu.be/abcdefghijk']);

    expect($item->fresh())->file->toBeNull()->file_size->toBeNull()->video_url->toBe('https://youtu.be/abcdefghijk')
        ->and(Storage::disk('local')->exists('library/lecture.mp3'))->toBeFalse();
});

it('replaces the file from the edit screen and removes the old one', function () {
    Storage::disk('local')->put('library/old.pdf', pdfContents());
    $item = LibraryItem::factory()->create(['file' => 'library/old.pdf']);
    $this->actingAs($this->editor);

    Livewire::test(EditLibraryItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['file' => [UploadedFile::fake()->createWithContent('new.pdf', pdfContents())]])
        ->call('save')
        ->assertHasNoFormErrors();

    $file = $item->fresh()->file;

    expect($file)->not->toBe('library/old.pdf')->toStartWith('library/')
        ->and(Storage::disk('local')->allFiles('library'))->toBe([$file]);
});

it('stores the thumbnail on the public disk and removes the replaced one', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateLibraryItem::class)
        ->fillForm(libraryForm(['thumbnail' => [UploadedFile::fake()->image('cover.jpg', 800, 500)]]))
        ->call('create')
        ->assertHasNoFormErrors();

    $item = LibraryItem::sole();
    $first = (string) $item->thumbnail;

    expect($first)->toStartWith('library/')
        ->and(Storage::disk('public')->exists($first))->toBeTrue();

    Livewire::test(EditLibraryItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['thumbnail' => [UploadedFile::fake()->image('new.jpg', 800, 500)]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Storage::disk('public')->exists($first))->toBeFalse()
        ->and(Storage::disk('public')->exists((string) $item->fresh()->thumbnail))->toBeTrue();
});

it('keeps the tags tidy: at most ten, short, trimmed and once each', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateLibraryItem::class)
        ->fillForm(libraryForm(['tags' => array_map(fn (int $number): string => 'تصنيف '.$number, range(1, 11))]))
        ->call('create')
        ->assertHasFormErrors(['tags']);

    Livewire::test(CreateLibraryItem::class)
        ->fillForm(libraryForm(['tags' => [str_repeat('س', 41)]]))
        ->call('create')
        ->assertHasFormErrors(['tags.0']);

    $item = app(SaveLibraryItem::class)->handle($this->editor, new LibraryItem, libraryForm(['tags' => [' الفلك ', 'الفلك', '']]));

    expect($item->tags)->toBe(['الفلك']);
});

it('soft deletes and restores an item with its file, and never deletes it for good', function () {
    Storage::disk('local')->put('library/kept.pdf', pdfContents());
    $item = LibraryItem::factory()->create(['file' => 'library/kept.pdf', 'slug' => 'kept']);
    $this->actingAs($this->editor);

    Livewire::test(ListLibraryItems::class)->callAction(TestAction::make('delete')->table($item));

    expect($item->fresh()->trashed())->toBeTrue()
        ->and(Storage::disk('local')->exists('library/kept.pdf'))->toBeTrue();
    $this->get('/library/books/kept')->assertNotFound();

    Livewire::test(ListLibraryItems::class)->filterTable('trashed', true)->callAction(TestAction::make('restore')->table($item));

    expect($item->fresh()->trashed())->toBeFalse()
        ->and(userWithRole(RoleName::SuperAdmin)->can('forceDelete', $item))->toBeFalse();
    $this->get('/library/books/kept')->assertOk();
});

it('refuses to save for someone without the permission, a file outside its folder, or the file of another item', function () {
    Storage::disk('local')->put('library/shared.pdf', pdfContents());
    Storage::disk('local')->put('other/keep.pdf', pdfContents());
    LibraryItem::factory()->create(['file' => 'library/shared.pdf'])->delete();
    $book = fn (string $file): array => libraryForm(['section' => LibrarySection::Books->value, 'file' => $file, 'slug' => 'book']);

    expect(fn () => app(SaveLibraryItem::class)->handle(userWithRole(RoleName::BoardMember), new LibraryItem, $book('library/shared.pdf')))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveLibraryItem::class)->handle($this->editor, new LibraryItem, $book('other/keep.pdf')))
        ->toThrow(ValidationException::class, __('validation.library_file'))
        ->and(fn () => app(SaveLibraryItem::class)->handle($this->editor, new LibraryItem, $book('library/shared.pdf')))
        ->toThrow(ValidationException::class, __('validation.file_in_use'))
        ->and(Storage::disk('local')->exists('other/keep.pdf'))->toBeTrue()
        ->and(Storage::disk('local')->exists('library/shared.pdf'))->toBeTrue()
        ->and(LibraryItem::withTrashed()->count())->toBe(1);
});

it('checks everything again when the action is called directly, not only in the form', function () {
    Storage::disk('local')->put('library/fake.mp3', pdfContents());
    Storage::disk('local')->put('library/book.exe', pdfContents());
    Storage::disk('public')->put('library/taken.jpg', 'صورة عنصر آخر');
    LibraryItem::factory()->video()->create(['thumbnail' => 'library/taken.jpg'])->delete();
    $save = fn (array $data) => app(SaveLibraryItem::class)->handle($this->editor, new LibraryItem, libraryForm($data));

    expect(fn () => $save(['section' => LibrarySection::Audio->value, 'file' => 'library/fake.mp3']))
        ->toThrow(ValidationException::class, __('validation.audio_file'))
        ->and(Storage::disk('local')->exists('library/fake.mp3'))->toBeFalse()
        // PDF بامتداد آخر لا يُقبل، فلا يصل الزائر ملف باسم مضلل
        ->and(fn () => $save(['section' => LibrarySection::Books->value, 'file' => 'library/book.exe']))
        ->toThrow(ValidationException::class, __('validation.real_pdf'))
        ->and(fn () => $save(['video_url' => 'https://evil.example/watch?v=abcdefghijk']))
        ->toThrow(ValidationException::class, __('validation.video_url'))
        ->and(fn () => $save(['video_url' => 'https://www.youtube.com/watch?v=abcdefghijk&x='.str_repeat('a', 500)]))
        ->toThrow(ValidationException::class, __('validation.video_url'))
        ->and(fn () => $save(['thumbnail' => 'library/taken.jpg']))
        ->toThrow(ValidationException::class, __('validation.image_in_use'))
        ->and(Storage::disk('public')->exists('library/taken.jpg'))->toBeTrue()
        ->and(fn () => $save(['section' => 'novels']))
        ->toThrow(ValidationException::class, __('validation.enum', ['attribute' => __('library.fields.section')]))
        ->and(fn () => $save(['tags' => array_map(fn (int $number): string => 'تصنيف '.$number, range(1, 11))]))
        ->toThrow(ValidationException::class)
        ->and(fn () => $save(['tags' => [str_repeat('س', 41)]]))
        ->toThrow(ValidationException::class)
        ->and(LibraryItem::count())->toBe(0);

    // وحجم الملف يُحسب من الملف نفسه، واسم تنزيل الكتاب PDF دائماً
    Storage::disk('local')->put('library/book.pdf', pdfContents());
    $book = $save(['section' => LibrarySection::Books->value, 'file' => 'library/book.pdf', 'file_size' => 1]);

    expect($book->file_size)->toBe(strlen(pdfContents()))
        ->and(LibraryItem::factory()->make(['slug' => 'book', 'file' => 'library/x.exe'])->downloadName())->toBe('book.pdf');
});

it('lets those who may view an item download its file from the panel', function () {
    Storage::disk('local')->put('library/draft.mp3', mp3Contents());
    $item = LibraryItem::factory()->audio()->unpublished()->create(['file' => 'library/draft.mp3', 'slug' => 'draft']);

    foreach ([$this->editor, userWithRole(RoleName::BoardMember)] as $user) {
        $this->actingAs($user);

        Livewire::test(ListLibraryItems::class)
            ->callAction(TestAction::make('downloadFile')->table($item))
            ->assertFileDownloaded('draft.mp3');
    }
});

it('lists the items without a date last, and filters them by section and by publishing', function () {
    $dated = LibraryItem::factory()->create(['title' => 'مؤرخ', 'published_on' => '2025-03-01']);
    $undated = LibraryItem::factory()->video()->create(['title' => 'بلا تاريخ', 'published_on' => null]);
    $draft = LibraryItem::factory()->unpublished()->create(['title' => 'مسودة', 'published_on' => '2024-06-01']);
    $this->actingAs($this->editor);

    Livewire::test(ListLibraryItems::class)
        ->assertCanSeeTableRecords([$dated, $draft, $undated], inOrder: true)
        ->filterTable('section', LibrarySection::Videos->value)
        ->assertCanSeeTableRecords([$undated])
        ->assertCanNotSeeTableRecords([$dated, $draft]);

    Livewire::test(ListLibraryItems::class)
        ->filterTable('is_published', false)
        ->assertCanSeeTableRecords([$draft])
        ->assertCanNotSeeTableRecords([$dated, $undated]);
});
