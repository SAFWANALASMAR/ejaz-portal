<?php

use App\Actions\Library\SaveLibraryItem;
use App\Enums\LibrarySection;
use App\Enums\RoleName;
use App\Filament\Resources\LibraryItems\LibraryItemResource;
use App\Filament\Resources\LibraryItems\Pages\CreateLibraryItem;
use App\Filament\Resources\LibraryItems\Pages\EditLibraryItem;
use App\Filament\Resources\LibraryItems\Pages\ListLibraryItems;
use App\Jobs\CountLibraryPages;
use App\Models\LibraryItem;
use App\Models\LibraryProgress;
use App\Models\MenuItem;
use App\Models\User;
use App\Support\StructuredData;
use Database\Seeders\LegacyRedirectsSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| مكتبة إعجاز بأقسامها الأربعة (التصور النهائي — البنود R9 وR20 وR24، docs/decisions/2026-11-02-library-four-sections.md):
| المرئية، والصوتية، و«الكتب والمؤلفات»، و«الأبحاث والدراسات» قسمان منفصلان في البيانات بقيد في القاعدة، وفي الواجهة واللوحة.
| والبحث يعامَل معاملة الكتاب في العارض والتنزيل و«للقراءة فقط».
*/

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function fourSectionsItem(array $attributes = []): LibraryItem
{
    $item = LibraryItem::factory()->create($attributes);

    if (filled($item->file)) {
        Storage::disk('local')->put((string) $item->file, pdfContents());
    }

    return $item;
}

function fourSectionsRow(string $slug, string $section): void
{
    DB::table('library_items')->insert(['slug' => $slug, 'title' => $slug, 'section' => $section, 'is_published' => false, 'created_at' => now(), 'updated_at' => now()]);
}

it('keeps the four sections in their public order with their Arabic labels', function () {
    expect(LibrarySection::values())->toBe(['videos', 'audio', 'books', 'research'])
        ->and(array_map(fn (LibrarySection $section): string => $section->getLabel(), LibrarySection::cases()))
        ->toBe(['المكتبة المرئية', 'المكتبة الصوتية', 'الكتب والمؤلفات', 'الأبحاث والدراسات'])
        ->and(LibrarySection::Research->takesPdf())->toBeTrue()
        ->and(LibrarySection::Research->takesVideoUrl())->toBeFalse();
});

it('turns a section outside the four into books, keeps research apart, and holds the four in the database', function () {
    $migration = require database_path('migrations/2026_11_02_140500_constrain_library_items_to_four_sections.php');
    $migration->down();

    fourSectionsRow('combined', 'books-research');
    fourSectionsRow('trashed-combined', 'publications');
    DB::table('library_items')->where('slug', 'trashed-combined')->update(['deleted_at' => now()]);
    fourSectionsRow('study', 'research');
    fourSectionsRow('talk', 'videos');
    fourSectionsRow('untidy-study', ' Research ');

    $migration->up();

    expect(DB::table('library_items')->orderBy('slug')->pluck('section', 'slug')->all())
        ->toBe(['combined' => 'books', 'study' => 'research', 'talk' => 'videos', 'trashed-combined' => 'books', 'untidy-study' => 'research'])
        ->and(LibraryItem::query()->where('slug', 'combined')->sole()->section)->toBe(LibrarySection::Books)
        // والقيد يرفض قسماً خامساً، داخل نقطة حفظ فلا تفسد معاملة الاختبار
        ->and(fn () => DB::transaction(fn () => fourSectionsRow('novel', 'novels')))->toThrow(QueryException::class);

    // والرجوع يرفع القيد وحده
    $migration->down();
    fourSectionsRow('novel', 'novels');
    expect(DB::table('library_items')->where('slug', 'novel')->value('section'))->toBe('novels');
    DB::table('library_items')->where('slug', 'novel')->delete();

    $migration->up();
    expect(fn () => DB::transaction(fn () => fourSectionsRow('novel', 'novels')))->toThrow(QueryException::class);
});

it('shows the four tiles and strips on the library page in order, books and research apart', function () {
    LibraryItem::factory()->video()->create(['title' => 'مقطع المكتبة']);
    LibraryItem::factory()->video()->create(['title' => 'صوتية المكتبة', 'section' => LibrarySection::Audio]);
    fourSectionsItem(['title' => 'كتاب المكتبة']);
    fourSectionsItem(['title' => 'بحث المكتبة', 'section' => LibrarySection::Research]);

    $html = $this->get('/library')->assertOk()->getContent();
    $tiles = (string) str($html)->after('data-library-tiles')->before('</ul>');
    $strips = (string) str($html)->after('data-library-strips');

    $labels = array_map(fn (LibrarySection $section): string => $section->getLabel(), LibrarySection::cases());
    $links = array_map(fn (LibrarySection $section): string => 'href="'.route('library.section', $section->value).'"', LibrarySection::cases());

    expect($tiles)->toContain(...$labels)
        ->and(strpos($tiles, $links[0]) < strpos($tiles, $links[1]) && strpos($tiles, $links[1]) < strpos($tiles, $links[2]) && strpos($tiles, $links[2]) < strpos($tiles, $links[3]))->toBeTrue();

    preg_match_all('/data-library-strip="([a-z]+)"/', $strips, $order);
    expect($order[1])->toBe(['videos', 'audio', 'books', 'research'])
        ->and((string) str($strips)->after('data-library-strip="books"')->before('data-library-strip="research"'))->toContain('كتاب المكتبة')->not->toContain('بحث المكتبة')
        ->and((string) str($strips)->after('data-library-strip="research"'))->toContain('بحث المكتبة')->not->toContain('كتاب المكتبة');
});

it('lists research in its own section with its search, tags and related items, and books without it', function () {
    fourSectionsItem(['title' => 'كتاب الفلك', 'tags' => ['الفلك']]);
    $study = fourSectionsItem(['slug' => 'big-bang', 'title' => 'دراسة الرتق', 'author' => 'باحث تجريبي', 'section' => LibrarySection::Research, 'tags' => ['الفلك']]);
    fourSectionsItem(['slug' => 'oceans', 'title' => 'دراسة البحار', 'section' => LibrarySection::Research, 'tags' => ['البحار']]);

    $this->get('/library/research')->assertOk()
        ->assertSeeInOrder(['<h1', __('library.sections.research')], false)
        ->assertSee('دراسة الرتق')->assertSee('دراسة البحار')->assertDontSee('كتاب الفلك')
        ->assertSee('href="'.route('library.section', ['research', 'tag' => 'الفلك']).'"', false);

    $this->get('/library/research?q=باحث')->assertOk()->assertSee('دراسة الرتق')->assertDontSee('دراسة البحار');
    $this->get('/library/research?tag=البحار')->assertOk()->assertSee('دراسة البحار')->assertDontSee('دراسة الرتق');
    $this->get('/library/books')->assertOk()->assertSee('كتاب الفلك')->assertDontSee('دراسة الرتق');

    // والعناصر ذات الصلة من القسم نفسه وحده، والرابط لا يعمل بقسم آخر
    $this->get('/library/research/big-bang')->assertOk()->assertSee('دراسة البحار')->assertDontSee('كتاب الفلك');
    $this->get('/library/books/big-bang')->assertNotFound();

    expect(StructuredData::libraryItem($study))->toMatchArray(['@type' => 'ScholarlyArticle', 'headline' => 'دراسة الرتق', 'name' => 'دراسة الرتق'])
        ->and(StructuredData::libraryItem(LibraryItem::query()->where('title', 'كتاب الفلك')->sole()))->toMatchArray(['@type' => 'Book'])->not->toHaveKey('headline');
});

it('sends the old books and research links permanently to their own sections', function () {
    $this->seed(LegacyRedirectsSeeder::class);

    $this->get('/books-and-publications')->assertStatus(301)->assertRedirect('/library/books');
    $this->get('/research-and-studies')->assertStatus(301)->assertRedirect('/library/research');
});

it('lists both sections and their published items in the sitemap', function () {
    fourSectionsItem(['slug' => 'first-book']);
    fourSectionsItem(['slug' => 'first-study', 'section' => LibrarySection::Research]);
    fourSectionsItem(['slug' => 'draft-study', 'section' => LibrarySection::Research, 'is_published' => false]);

    $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain('<loc>'.url('/library/books').'</loc>', '<loc>'.url('/library/research').'</loc>', '<loc>'.url('/library/books/first-book').'</loc>', '<loc>'.url('/library/research/first-study').'</loc>')
        ->not->toContain('draft-study');
});

// والأبحاث خرجت من شريط الرئيسية (ملاحظات المدير ليلة 5 نوفمبر 2026، البند 3): شريط واحد للكتب، وقسمها باقٍ في المكتبة
it('shows book covers in the home strip, leading to the library, and keeps research in its library section', function () {
    fourSectionsItem(['title' => 'غلاف كتاب', 'thumbnail' => 'library/thumbs/b.jpg']);
    fourSectionsItem(['title' => 'غلاف بحث', 'section' => LibrarySection::Research, 'thumbnail' => 'library/thumbs/r.jpg']);

    $html = $this->get('/')->assertOk()->getContent();
    $books = (string) str($html)->after('id="home-strip-books"')->before('</section>');

    expect($books)->toContain('غلاف كتاب', 'href="'.route('library.index').'"')
        ->and($html)->not->toContain('غلاف بحث');

    $this->get(route('library.section', LibrarySection::Research->value))->assertOk()->assertSee('غلاف بحث');
});

it('shows research covers in the account library strip too', function () {
    fourSectionsItem(['title' => 'غلاف بحث للمستفيد', 'section' => LibrarySection::Research, 'thumbnail' => 'library/thumbs/r.jpg']);

    $html = (string) $this->actingAs(User::factory()->create())->get('/account')->assertOk()->getContent();

    expect((string) str($html)->after('data-account-library-strip')->before('</section>'))->toContain('غلاف بحث للمستفيد');
});

it('treats a read-only study like a read-only book: the page reader and no download', function () {
    $study = fourSectionsItem(['slug' => 'study', 'title' => 'دراسة للقراءة', 'section' => LibrarySection::Research, 'is_downloadable' => false]);

    $this->get('/library/research/study')->assertOk()
        ->assertSee('data-pdf-reader="'.route('library.file', ['research', 'study']).'"', false)
        ->assertSee(__('library.item.read_only'))
        ->assertDontSee(route('library.download', ['research', 'study']))
        ->assertDontSee('<iframe', false);

    $this->get('/library/research/study/download')->assertNotFound();
    readerFetch('/library/research/study/file', readerTicket($study))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/library/research/study/file')->assertNotFound();
    $this->get('/library/research/study/file', ['Sec-Fetch-Dest' => 'document'])->assertRedirect(route('library.show', ['research', 'study']));
});

it('counts the pages of a read-only study and records the pages its reader sees, like a book', function () {
    $study = fourSectionsItem(['slug' => 'study', 'section' => LibrarySection::Research, 'is_downloadable' => false]);
    app()->call([new CountLibraryPages($study->id), 'handle']);
    $reader = User::factory()->create();

    expect($study->refresh()->page_count)->toBe(1);

    $this->actingAs($reader)->get('/library/research/study')->assertOk()->assertSee('data-progress="'.route('library.progress', ['research', 'study']).'"', false);
    $this->postJson(route('library.progress', ['research', 'study']), ['pages' => [1], 'page' => 1])->assertOk()->assertExactJson(['pages' => [1]]);

    $progress = LibraryProgress::query()->whereBelongsTo($reader)->where('library_item_id', $study->id)->sole();
    expect($progress->pages_count)->toBe(1)->and($progress->completed_at)->not->toBeNull();
});

it('lets a downloadable study open in the viewer and download as an attachment', function () {
    fourSectionsItem(['slug' => 'study', 'section' => LibrarySection::Research]);

    $this->get('/library/research/study')->assertOk()
        ->assertSee('<iframe src="'.route('library.file', ['research', 'study']).'"', false)
        ->assertSee('href="'.route('library.download', ['research', 'study']).'"', false);

    expect($this->get('/library/research/study/download')->assertOk()->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('creates an item of each section from the panel, with its own source, and logs it', function (LibrarySection $section) {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    $source = $section->takesPdf()
        ? ['file' => [UploadedFile::fake()->createWithContent('item.pdf', pdfContents())], 'is_downloadable' => false]
        : ['video_url' => 'https://www.youtube.com/watch?v=abcdefghijk'];

    Livewire::test(CreateLibraryItem::class)
        ->fillForm(['title' => 'عنصر '.$section->value, 'slug' => 'item-'.$section->value, 'section' => $section->value, 'is_published' => true, ...$source])
        ->call('create')
        ->assertHasNoFormErrors();

    $item = LibraryItem::query()->where('slug', 'item-'.$section->value)->sole();

    expect($item->section)->toBe($section)
        ->and($item->isReadOnly())->toBe($section->takesPdf())
        ->and(Activity::whereMorphedTo('subject', $item)->where('event', 'created')->exists())->toBeTrue();

    $this->get('/library/'.$section->value.'/item-'.$section->value)->assertOk();
})->with(LibrarySection::cases());

it('moves a book to research from the edit screen with its file, and filters the table by each section', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    $book = fourSectionsItem(['slug' => 'moved', 'title' => 'كتاب ينتقل']);
    $other = fourSectionsItem(['title' => 'كتاب باقٍ']);
    $video = LibraryItem::factory()->video()->create(['title' => 'مقطع']);
    $this->actingAs($editor);

    Livewire::test(EditLibraryItem::class, ['record' => $book->getRouteKey()])
        ->fillForm(['section' => LibrarySection::Research->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($book->fresh())->section->toBe(LibrarySection::Research)->file->toBe($book->file)
        ->and(Activity::whereMorphedTo('subject', $book)->where('event', 'updated')->exists())->toBeTrue();
    $this->get('/library/research/moved')->assertOk();
    $this->get('/library/books/moved')->assertNotFound();

    Livewire::test(ListLibraryItems::class)
        ->assertTableFilterExists('section')
        ->filterTable('section', LibrarySection::Research->value)
        ->assertCanSeeTableRecords([$book->fresh()])
        ->assertCanNotSeeTableRecords([$other, $video])
        ->filterTable('section', LibrarySection::Books->value)
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$book->fresh(), $video]);
});

it('keeps the panel permissions as they were for research', function () {
    $study = fourSectionsItem(['section' => LibrarySection::Research]);

    $this->actingAs(userWithRole(RoleName::Secretary))->get(LibraryItemResource::getUrl('index'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(LibraryItemResource::getUrl('edit', ['record' => $study]))->assertForbidden();

    expect(fn () => app(SaveLibraryItem::class)->handle(userWithRole(RoleName::BoardMember), $study, ['section' => LibrarySection::Books->value]))
        ->toThrow(AuthorizationException::class)
        ->and($study->fresh()->section)->toBe(LibrarySection::Research);
});

it('renames the default books menu item to the section name, lists the four sections in the dropdown, and leaves an edited one alone', function () {
    $this->seed(MenuSeeder::class);
    $migration = require database_path('migrations/2026_11_02_140600_rename_books_menu_item.php');
    $books = fn () => MenuItem::query()->where('url', '/library/books')->sole();
    $books()->update(['label' => 'الكتب']);

    $migration->up();
    expect($books()->label)->toBe('الكتب والمؤلفات');

    preg_match('/<nav data-main-nav.*?<\/nav>/s', (string) $this->get('/')->assertOk()->getContent(), $nav);
    $library = (string) str($nav[0])->after('مكتبة إعجاز');
    $positions = array_map(fn (LibrarySection $section): int|false => strpos($library, 'href="'.url('/library/'.$section->value).'"'), LibrarySection::cases());
    expect($library)->toContain('الكتب والمؤلفات', 'الأبحاث والدراسات')
        ->and($positions)->not->toContain(false)
        ->and($positions)->toBe(collect($positions)->sort()->values()->all());

    $migration->down();
    expect($books()->label)->toBe('الكتب');

    // وما عدّله المحرر لا يُمس
    $books()->update(['label' => 'مؤلفات الجمعية']);
    $migration->up();
    expect($books()->label)->toBe('مؤلفات الجمعية');
});
