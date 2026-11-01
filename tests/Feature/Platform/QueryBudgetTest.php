<?php

use App\Enums\RoleName;
use App\Filament\Resources\ArchiveCategories\ArchiveCategoryResource;
use App\Filament\Resources\ArchiveCategories\Pages\ManageArchiveCategories;
use App\Filament\Resources\ProgramTypes\Pages\ManageProgramTypes;
use App\Filament\Resources\ProgramTypes\ProgramTypeResource;
use App\Models\ArchiveCategory;
use App\Models\ArchiveItem;
use App\Models\Document;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\ContentCache;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
| سقف الاستعلامات لكل صفحة (docs/performance.md: «لا استعلام داخل حلقة»). كل صفحة تُطلب بقليل من
| السجلات ثم بكثير منها، فيبقى العدد نفسه، ولا يتجاوز سقفها. والصفحات العامة تُقاس بلا تخزين مؤقت.
| (docs/reviews/2026-10-13-performance.md)
*/

/**
 * عدد الاستعلامات في الطلب. والطلب يُرسل مرة قبلها، فلا تُحسب استعلامات أول تشغيل.
 */
function queriesFor(Closure $request, bool $cold = true): int
{
    $request();

    if ($cold) {
        ContentCache::flush();
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $request()->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/**
 * العدد بقليل من السجلات يساوي العدد بكثير منها، ولا يتجاوز السقف.
 */
function expectQueryBudget(Closure $seed, Closure $request, int $max): void
{
    $seed(2);
    $few = queriesFor($request);

    $seed(25);
    $many = queriesFor($request);

    expect($many)->toBe($few)->toBeLessThanOrEqual($max);
}

// وكل سقف +1 منذ شعارات الشركاء في التذييل (مخزّنة مع المحتوى، فتُسأل مرة بعد مسحه)، والرئيسية +5 منذ بنية التصور النهائي:
// صور العارض، والجوائز، والمستجدات من البرامج والمسابقات والمكتبة والحوكمة (docs/decisions/2026-10-30-visual-refresh-2.md)،
// والرئيسية +1 بالنسخة 63: الشركاء ظاهرون افتراضياً، وحالة نشر «عن الجمعية» لرابط بطاقة «نحو فهم أعمق»، بلا الحسابات الرسمية
it('keeps the public lists within a fixed number of queries', function (string $url, Closure $seed, int $max) {
    expectQueryBudget($seed, fn () => $this->get($url), $max);
})->with([
    'news' => ['/media/news', fn (int $n) => NewsArticle::factory()->count($n)->create(), 4],
    'library section' => ['/library/videos', fn (int $n) => LibraryItem::factory()->count($n)->create(), 3],
    'governance' => ['/governance', fn (int $n) => Document::factory()->count($n)->create(), 4],
    'home' => ['/', fn (int $n) => NewsArticle::factory()->count($n)->create(), 12],
    'programs' => ['/programs', fn (int $n) => Program::factory()->count($n)->create(), 4],
]);

it('serves the cached public pages without queries', function () {
    NewsArticle::factory()->count(5)->create();

    foreach (['/', '/media/news', '/library', '/governance', '/initiatives', '/media/albums', '/media/partners'] as $url) {
        expect(queriesFor(fn () => $this->get($url), cold: false))->toBe(0, $url);
    }
});

it('loads the programs list types once with their programs', function () {
    $type = ProgramType::factory()->create(['name' => 'دورة تجريبية']);
    Program::factory()->count(3)->create(['program_type_id' => $type->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get('/programs')->assertOk()->assertSee('دورة تجريبية');
    $types = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "program_types"'));
    DB::disableQueryLog();

    expect($types)->toHaveCount(1);
});

it('keeps the account pages within a fixed number of queries', function (string $url, int $max) {
    $user = User::factory()->create();

    $seed = function (int $n) use ($user): void {
        foreach (Program::factory()->count($n)->create() as $program) {
            ProgramRegistration::factory()->create(['program_id' => $program->id, 'user_id' => $user->id]);
        }

        ServiceRequest::factory()->count($n)->create(['user_id' => $user->id]);
    };

    expectQueryBudget($seed, fn () => $this->actingAs($user)->get($url), $max);
})->with([
    // و14 منذ بطاقات لوحة المستفيد من اللوحة (AccountCard — 25 أكتوبر 2026)، و+1 منذ شعارات الشركاء في التذييل
    'dashboard' => ['/account', 15],
    'programs' => ['/account/programs', 6],
    'requests' => ['/account/requests', 5],
]);

it('keeps the program types table to one query for all rows', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);

    expectQueryBudget(
        fn (int $n) => Program::factory()->count($n)->create(['program_type_id' => fn () => ProgramType::factory()->create()->id]),
        fn () => $this->actingAs($admin)->get(ProgramTypeResource::getUrl('index')),
        8,
    );
});

it('keeps the archive categories table to one query for all rows', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);

    expectQueryBudget(
        fn (int $n) => ArchiveItem::factory()->count($n)->create(),
        fn () => $this->actingAs($admin)->get(ArchiveCategoryResource::getUrl('index')),
        8,
    );
});

it('still hides deleting a program type that has programs, even deleted ones', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));
    $empty = ProgramType::factory()->create();
    $used = ProgramType::factory()->create();
    Program::factory()->create(['program_type_id' => $used->id])->delete();

    Livewire::test(ManageProgramTypes::class)
        ->assertActionVisible(TestAction::make('delete')->table($empty))
        ->assertActionHidden(TestAction::make('delete')->table($used));
});

it('still refuses deleting an archive category that has items, even deleted ones', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));
    $empty = ArchiveCategory::factory()->create();
    $used = ArchiveCategory::factory()->create();
    ArchiveItem::factory()->create(['archive_category_id' => $used->id])->delete();

    Livewire::test(ManageArchiveCategories::class)
        ->assertActionVisible(TestAction::make('delete')->table($empty))
        ->assertActionHidden(TestAction::make('delete')->table($used));

    // والصلاحية خارج الجدول تسأل القاعدة نفسها
    expect(auth()->user()->can('delete', $used->fresh()))->toBeFalse()
        ->and(auth()->user()->can('delete', $empty->fresh()))->toBeTrue();
});

it('indexes the sorted and joined columns of the large tables', function (string $table, string $columns) {
    $indexed = collect(DB::select('select indexdef from pg_indexes where schemaname = current_schema() and tablename = ?', [$table]))
        ->contains(fn (object $index): bool => str_contains($index->indexdef, '('.$columns.')'));

    expect($indexed)->toBeTrue();
})->with([
    ['activity_log', 'created_at'],
    ['users', 'created_at'],
    ['payments', 'created_at'],
    ['inquiries', 'created_at'],
    ['competition_submissions', 'competition_id, submitted_at'],
    ['archive_items', 'archive_category_id, dated_on'],
    ['archive_items', 'program_id'],
    ['form_entries', 'form_template_version_id'],
]);
