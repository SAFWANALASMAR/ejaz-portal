<?php

use App\Actions\Competitions\SaveCompetition;
use App\Actions\Library\SaveLibraryItem;
use App\Enums\LibrarySection;
use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\LibraryItem;
use App\Support\CompetitionPage;
use Database\Seeders\BrahinCompetitionSeeder;
use Illuminate\Support\Facades\Storage;

/*
| كتاب «خارج قوائم المكتبة» (قرار صفوان، 13 نوفمبر 2026: «مكانه ليس المكتبة» —
| docs/decisions/2026-11-13-fix-brahin-guide-outside-library.md): منشور ويُفتح برابطه وحده، مثل دليل مسابقة البراهين من
| صفحتها، ولا يظهر في المكتبة ولا بحثها ولا العناصر ذات الصلة ولا خريطة الموقع، وصفحته لا تُفهرس.
*/

beforeEach(fn () => Storage::fake('local'));

it('keeps an unlisted book out of the library lists, search, related items and sitemap, but opens it by its link', function () {
    $listed = LibraryItem::factory()->create(['slug' => 'listed-book', 'title' => 'كتاب ظاهر في المكتبة']);
    LibraryItem::factory()->create(['slug' => 'brahin-guide', 'title' => 'دليل اختبار مسابقة البراهين', 'is_unlisted' => true, 'is_downloadable' => false, 'tags' => ['وسم الدليل وحده']]);

    $this->get('/library')->assertOk()->assertSee('كتاب ظاهر في المكتبة')->assertDontSee('دليل اختبار مسابقة البراهين');
    $this->get('/library/books')->assertOk()->assertSee('كتاب ظاهر في المكتبة')->assertDontSee('دليل اختبار مسابقة البراهين')->assertDontSee('وسم الدليل وحده');
    $this->get('/')->assertOk()->assertDontSee('دليل اختبار مسابقة البراهين');
    $this->get('/library/books?q=دليل')->assertOk()->assertDontSee('دليل اختبار مسابقة البراهين');
    $this->get('/library/books/listed-book')->assertOk()->assertDontSee('دليل اختبار مسابقة البراهين');
    $this->get('/sitemap.xml')->assertOk()->assertSee('listed-book')->assertDontSee('brahin-guide');

    $this->get('/library/books/brahin-guide')->assertOk()
        ->assertSee('دليل اختبار مسابقة البراهين')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    $this->get('/library/books/'.$listed->slug)->assertDontSee('<meta name="robots" content="noindex', false);
});

it('still shows the guide button on the competition page when its book is unlisted', function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);
    (new BrahinCompetitionSeeder)->run();
    $competition = Competition::query()->where('slug', 'brahin-1448')->sole();
    $competition->forceFill(['is_published' => true])->save();
    $book = LibraryItem::factory()->create(['slug' => 'brahin-guide', 'is_unlisted' => true, 'is_downloadable' => false]);

    app(SaveCompetition::class)->handle(userWithRole(RoleName::ExecutiveDirector), $competition, [
        'title' => $competition->title, 'slug' => $competition->slug, 'student_mode' => true,
        'opens_at' => $competition->opens_at->toDateTimeString(), 'closes_at' => $competition->closes_at->toDateTimeString(),
        'page_content' => [...$competition->page_content, CompetitionPage::GUIDE_BOOK => (string) $book->id],
    ]);

    $this->get('/competitions/brahin-1448')->assertOk()
        ->assertSee('data-guide-book', false)
        ->assertSee('href="'.route('library.show', [LibrarySection::Books->value, 'brahin-guide']).'"', false);
});

it('saves the flag for books from the panel action, and clears it for other sections', function () {
    $editor = userWithRole(RoleName::TechnicalAdmin);
    $book = LibraryItem::factory()->create(['slug' => 'book']);

    $saved = app(SaveLibraryItem::class)->handle($editor, $book, ['title' => $book->title, 'section' => LibrarySection::Books->value, 'is_unlisted' => true]);
    expect($saved->fresh()->is_unlisted)->toBeTrue();

    $video = LibraryItem::factory()->video()->create(['slug' => 'clip']);
    $saved = app(SaveLibraryItem::class)->handle($editor, $video, ['title' => $video->title, 'section' => LibrarySection::Videos->value, 'is_unlisted' => true]);
    expect($saved->fresh()->is_unlisted)->toBeFalse();
});

it('keeps an unlisted book out of the assistant index, and back in once listed', function () {
    $book = LibraryItem::factory()->create(['slug' => 'brahin-guide', 'is_unlisted' => true]);
    \App\Support\Assistant\AssistantIndex::rebuild();

    expect(\Illuminate\Support\Facades\DB::table('assistant_chunks')->where('url', 'like', '%brahin-guide%')->exists())->toBeFalse();

    $book->forceFill(['is_unlisted' => false])->save();
    \App\Support\Assistant\AssistantIndex::rebuild();

    expect(\Illuminate\Support\Facades\DB::table('assistant_chunks')->where('url', 'like', '%brahin-guide%')->exists())->toBeTrue();
});

it('keeps every existing item listed by default', function () {
    expect(LibraryItem::factory()->create()->fresh()->is_unlisted)->toBeFalse();
});
