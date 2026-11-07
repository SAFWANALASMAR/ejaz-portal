<?php

use App\Actions\Competitions\SaveCompetition;
use App\Enums\LibrarySection;
use App\Enums\RoleName;
use App\Filament\Resources\Competitions\Pages\EditCompetition;
use App\Models\Competition;
use App\Models\LibraryItem;
use App\Models\User;
use App\Support\CompetitionPage;
use App\Support\ReaderFileResponse;
use App\Support\SchoolNetworkLimits;
use Database\Seeders\BrahinCompetitionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
| دليل الجمعية كاملاً في صفحة مسابقة الطلاب (قرار صفوان والمدير التنفيذي، 7 نوفمبر 2026 —
| docs/decisions/2026-11-07-brahin-guide-id-time.md): كتاب منشور في المكتبة «للقراءة فقط» يُختار من شاشة المسابقة، وزر
| «اقرأ الدليل كاملاً» تحت خطوات الدخول يفتح صفحته بقارئ المكتبة، ويُخفى إن لم يُختر أو لم يعد منشوراً للقراءة فقط.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);

    (new BrahinCompetitionSeeder)->run();
    $this->competition = Competition::query()->where('slug', 'brahin-1448')->sole();
    $this->competition->forceFill(['is_published' => true])->save();
    $this->director = userWithRole(RoleName::ExecutiveDirector);
    $this->book = LibraryItem::factory()->create(['slug' => 'brahin-guide', 'title' => 'دليل الدخول إلى الاختبار', 'is_downloadable' => false]);
});

/**
 * حفظ المسابقة من الإجراء نفسه الذي تستدعيه اللوحة، بنصوصها كما هي واختيار الكتاب.
 */
function saveGuideBook(User $actor, Competition $competition, mixed $book): Competition
{
    return app(SaveCompetition::class)->handle($actor, $competition, [
        'title' => $competition->title, 'slug' => $competition->slug, 'student_mode' => true,
        'opens_at' => $competition->opens_at->toDateTimeString(), 'closes_at' => $competition->closes_at->toDateTimeString(),
        'page_content' => [...$competition->page_content, CompetitionPage::GUIDE_BOOK => $book],
    ]);
}

it('saves a read-only published book as the full guide, and links it under the entry steps on the competition and guide pages', function () {
    $saved = saveGuideBook($this->director, $this->competition, (string) $this->book->id);
    $link = route('library.show', [LibrarySection::Books->value, 'brahin-guide']);

    expect($saved->page_content[CompetitionPage::GUIDE_BOOK])->toBe($this->book->id)
        ->and($saved->guideBook()?->is($this->book))->toBeTrue();

    foreach (['/competitions/brahin-1448', '/competitions/brahin-1448/guide'] as $page) {
        $this->get($page)->assertOk()->assertSee('اقرأ الدليل كاملاً')->assertSee('href="'.$link.'"', false)->assertSee('data-guide-book', false);
    }

    // صفحة الكتاب بقارئ المكتبة، بلا تنزيل
    $this->get($link)->assertOk()->assertSee('data-pdf-reader', false);
    $this->get($link.'/download')->assertNotFound();
});

it('refuses a downloadable, unpublished, non-book or missing item as the full guide', function (array|int|string $item) {
    // مصفوفة: عنصر مكتبة بهذه الصفات، وإلا القيمة كما هي
    $value = is_array($item) ? LibraryItem::factory()->create($item)->id : $item;

    expect(fn () => saveGuideBook($this->director, $this->competition, $value))->toThrow(ValidationException::class)
        ->and($this->competition->fresh()->guideBook())->toBeNull();
})->with([
    'downloadable' => [['is_downloadable' => true]],
    'unpublished' => [['is_published' => false, 'is_downloadable' => false]],
    'a video' => [['section' => LibrarySection::Videos, 'video_url' => 'https://www.youtube.com/watch?v=abcdefghijk', 'file' => null, 'is_downloadable' => false]],
    'missing' => [999999],
    'not a number' => ['brahin-guide'],
]);

it('lets only who can edit the competition choose the full guide', function () {
    expect(fn () => saveGuideBook(User::factory()->create(), $this->competition, $this->book->id))->toThrow(AuthorizationException::class)
        ->and($this->competition->fresh()->guideBook())->toBeNull();
});

it('hides the button when no book is chosen, or the chosen one is unpublished or made downloadable', function (array $change) {
    $this->competition->forceFill(['page_content' => [...$this->competition->page_content, CompetitionPage::GUIDE_BOOK => $this->book->id]])->save();
    $this->get('/competitions/brahin-1448')->assertSee('data-guide-book', false);

    $change === [] ? $this->competition->forceFill(['page_content' => array_diff_key($this->competition->page_content, [CompetitionPage::GUIDE_BOOK => true])])->save() : $this->book->forceFill($change)->save();

    $this->get('/competitions/brahin-1448')->assertOk()->assertDontSee('data-guide-book', false)->assertDontSee('اقرأ الدليل كاملاً');
    $this->get('/competitions/brahin-1448/guide')->assertOk()->assertDontSee('data-guide-book', false);
})->with([
    'not chosen' => [[]],
    'unpublished' => [['is_published' => false]],
    'downloadable' => [['is_downloadable' => true]],
    'deleted' => [['deleted_at' => now()]],
]);

// مراجعتا #217، م١: كتاب الدليل المحفوظ لا يمنع حفظ تعديل آخر في المسابقة بعد أن تتغيّر حالته، والزر مخفي
it('still saves the competition when the chosen guide book was later unpublished, and keeps the button hidden', function () {
    saveGuideBook($this->director, $this->competition, $this->book->id);
    $this->book->forceFill(['is_published' => false])->save();

    $saved = saveGuideBook($this->director, $this->competition->fresh(), $this->book->id);

    expect($saved->page_content[CompetitionPage::GUIDE_BOOK])->toBe($this->book->id)
        ->and($saved->guideBook())->toBeNull();
    $this->get('/competitions/brahin-1448')->assertOk()->assertDontSee('data-guide-book', false);

    // واختيار كتاب آخر غير صالح يُرفض كما هو
    $other = LibraryItem::factory()->create(['is_downloadable' => true]);
    expect(fn () => saveGuideBook($this->director, $this->competition->fresh(), $other->id))->toThrow(ValidationException::class);
});

it('accepts a read-only research paper as the full guide', function () {
    $paper = LibraryItem::factory()->create(['section' => LibrarySection::Research, 'is_downloadable' => false]);

    saveGuideBook($this->director, $this->competition, $paper->id);

    expect($this->competition->fresh()->guideBook()?->is($paper))->toBeTrue();
});

it('offers only read-only published books in the competition screen of the panel', function () {
    LibraryItem::factory()->create(['title' => 'كتاب قابل للتحميل', 'is_downloadable' => true]);
    LibraryItem::factory()->unpublished()->create(['title' => 'كتاب مسودة', 'is_downloadable' => false]);
    $this->actingAs($this->director);

    Livewire::test(EditCompetition::class, ['record' => $this->competition->getRouteKey()])
        ->assertFormFieldExists('page_content.'.CompetitionPage::GUIDE_BOOK, function ($field): bool {
            return array_values($field->getOptions()) === ['دليل الدخول إلى الاختبار'];
        });
});

// حد قارئ الدليل لشبكات المدارس (قرار صفوان، 7 نوفمبر 2026): فصل كامل خلف عنوان مدرسة واحد يفتح الدليل معاً أثناء نوافذ
// المسابقة، والكتب الأخرى وخارج النوافذ بحد القارئ العادي (ReaderFileResponse::PER_MINUTE)
it('lets a whole class behind one school address open the guide book during the competition windows, but keeps the normal limit for other books', function () {
    Storage::fake('local');
    $this->book->forceFill(['slug' => 'book'])->save();
    Storage::disk(LibraryItem::disk())->put((string) $this->book->file, pdfContents());
    $other = LibraryItem::factory()->create(['slug' => 'other-book', 'is_downloadable' => false]);
    Storage::disk(LibraryItem::disk())->put((string) $other->file, pdfContents());
    saveGuideBook($this->director, $this->competition, $this->book->id);
    $this->competition->forceFill(['opens_at' => now()->subDay(), 'closes_at' => now()->addMonth()])->save();
    SchoolNetworkLimits::forget();

    expect(SchoolNetworkLimits::registrationOpen())->toBeTrue();

    foreach (range(1, 30) as $i) {
        $this->get('/library/books/book/file', ['Sec-Fetch-Dest' => 'empty'])->assertOk();
    }

    foreach (range(1, ReaderFileResponse::PER_MINUTE) as $i) {
        $this->get('/library/books/other-book/file', ['Sec-Fetch-Dest' => 'empty'])->assertOk();
    }
    $this->get('/library/books/other-book/file', ['Sec-Fetch-Dest' => 'empty'])->assertStatus(429);
});

it('keeps the normal reader limit on the guide book outside the competition windows', function () {
    Storage::fake('local');
    $this->book->forceFill(['slug' => 'book'])->save();
    Storage::disk(LibraryItem::disk())->put((string) $this->book->file, pdfContents());
    saveGuideBook($this->director, $this->competition, $this->book->id);
    $this->competition->forceFill(['opens_at' => now()->subMonths(2), 'closes_at' => now()->subMonth()])->save();
    $this->competition->rounds()->update(['starts_at' => now()->subMonths(2), 'ends_at' => now()->subMonths(2)->addHour()]);
    SchoolNetworkLimits::forget();

    expect(SchoolNetworkLimits::registrationOpen())->toBeFalse()->and(SchoolNetworkLimits::examWindow())->toBeFalse();

    foreach (range(1, ReaderFileResponse::PER_MINUTE) as $i) {
        $this->get('/library/books/book/file', ['Sec-Fetch-Dest' => 'empty'])->assertOk();
    }
    $this->get('/library/books/book/file', ['Sec-Fetch-Dest' => 'empty'])->assertStatus(429);
});

// مراجعة #218، م٢: الكتاب يُربط دليلاً بعد فتحه، فيتسع حده فوراً لا بعد دقيقة
it('widens the reader limit right after the book is chosen as the guide, even if it was opened before', function () {
    Storage::fake('local');
    $this->book->forceFill(['slug' => 'book'])->save();
    Storage::disk(LibraryItem::disk())->put((string) $this->book->file, pdfContents());
    $this->competition->forceFill(['opens_at' => now()->subDay(), 'closes_at' => now()->addMonth()])->save();
    SchoolNetworkLimits::forget();

    $this->get('/library/books/book/file', ['Sec-Fetch-Dest' => 'empty'])->assertOk();
    saveGuideBook($this->director, $this->competition->fresh(), $this->book->id);

    foreach (range(1, 30) as $i) {
        $this->get('/library/books/book/file', ['Sec-Fetch-Dest' => 'empty'])->assertOk();
    }
});
