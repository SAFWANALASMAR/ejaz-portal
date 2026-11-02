<?php

use App\Enums\RoleName;
use App\Filament\Resources\ArchiveCategories\ArchiveCategoryResource;
use App\Filament\Resources\ArchiveCategories\Pages\ManageArchiveCategories;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Pages\EditCompetition;
use App\Filament\Resources\ProgramTypes\Pages\ManageProgramTypes;
use App\Filament\Resources\ProgramTypes\ProgramTypeResource;
use App\Models\Achievement;
use App\Models\ArchiveCategory;
use App\Models\ArchiveItem;
use App\Models\Competition;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
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

// والرئيسية +5 منذ بنية التصور النهائي:
// صور العارض، والجوائز، والمستجدات من البرامج والمسابقات والمكتبة والحوكمة (docs/decisions/2026-10-30-visual-refresh-2.md)،
// والرئيسية +1 بالنسخة 63: الشركاء ظاهرون افتراضياً، وحالة نشر «عن الجمعية» لرابط بطاقة «نحو فهم أعمق»، بلا الحسابات الرسمية
it('keeps the public lists within a fixed number of queries', function (string $url, Closure $seed, int $max) {
    expectQueryBudget($seed, fn () => $this->get($url), $max);
})->with([
    'news' => ['/media/news', fn (int $n) => NewsArticle::factory()->count($n)->create(), 3],
    'library section' => ['/library/videos', fn (int $n) => LibraryItem::factory()->count($n)->create(), 2],
    // والحوكمة +1 منذ عدد أعضاء الجمعية العمومية الفعّالين (AssemblyMembersCount، مخزّن مع المحتوى — 1 نوفمبر 2026)
    'governance' => ['/governance', fn (int $n) => Document::factory()->count($n)->create(), 4],
    'home' => ['/', fn (int $n) => NewsArticle::factory()->count($n)->create(), 11],
    'programs' => ['/programs', fn (int $n) => Program::factory()->count($n)->create(), 3],
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

        // و«قراءاتي» بصفوفها، فيُحسب تحميل عناصرها مسبقاً (docs/reviews/2026-11-02-v0.15-perf.md)
        foreach (LibraryItem::factory()->count($n)->create() as $item) {
            readingProgressFor($user, $item);
        }
    };

    expectQueryBudget($seed, fn () => $this->actingAs($user)->get($url), $max);
})->with([
    // و14 منذ بطاقات لوحة المستفيد من اللوحة (AccountCard — 25 أكتوبر 2026)، و+2 منذ شريط المكتبة تحت الترحيب (المقاطع والأغلفة، مخزّن مع المحتوى فيُسأل مرة بعد مسحه)
    // و+2 منذ «قراءاتي»: أحدث التقدّم ومجاميع صاحب الحساب (docs/decisions/2026-11-01-my-reading.md)
    // وبصفوف «قراءاتي» +1 لتحميل عناصرها، و-1 بعدّ التسجيلات المؤكدة والمنجزة باستعلام واحد (docs/reviews/2026-11-02-v0.15-perf.md)
    'dashboard' => ['/account', 18],
    'programs' => ['/account/programs', 5],
    'requests' => ['/account/requests', 4],
    'my reading' => ['/account/my-reading', 4],
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

/*
| صفحات النسخة 0.15 (docs/reviews/2026-11-02-v0.15-perf.md): القوائم والصفحات المفردة بلا تخزين، وصفحة مسابقة الطلاب
| بمراحلها ومسجليها، ونموذج المسابقة في اللوحة بجدول جوائزها.
*/

/**
 * تقدّم صاحب الحساب في عنصر من المكتبة كما يكتبه RecordLibraryProgress.
 */
function readingProgressFor(User $user, LibraryItem $item): void
{
    DB::table('library_progress')->insert([
        'user_id' => $user->id,
        'library_item_id' => $item->id,
        'pages_seen' => '1-3',
        'pages_count' => 3,
        'last_page' => 3,
        'seconds_consumed' => 0,
        'last_reported_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('keeps the v0.15 public lists within a fixed number of queries', function (string $url, Closure $seed, int $max) {
    expectQueryBudget($seed, fn () => $this->get($url), $max);
})->with([
    'library' => ['/library', fn (int $n) => [LibraryItem::factory()->count($n)->create(), LibraryItem::factory()->count($n)->video()->create(), LibraryItem::factory()->count($n)->audio()->create()], 4],
    'competitions' => ['/competitions', fn (int $n) => [
        Competition::factory()->count($n)->create(),
        Competition::factory()->count($n)->upcoming()->create(),
        Competition::factory()->count($n)->closed()->create(),
    ], 4],
    'achievements' => ['/achievements', fn (int $n) => Achievement::factory()->count($n)->published()->create(), 2],
]);

it('keeps the public detail pages within a fixed number of queries', function (string $model, string $prefix, int $max) {
    $target = $model::factory()->create();

    expectQueryBudget(fn (int $n) => $model::factory()->count($n)->create(), fn () => $this->get($prefix.$target->slug), $max);
})->with([
    'library book' => [LibraryItem::class, '/library/books/', 3],
    'news article' => [NewsArticle::class, '/media/news/', 3],
    'governance document' => [Document::class, '/governance/', 2],
    'program' => [Program::class, '/programs/', 2],
    'competition' => [Competition::class, '/competitions/', 1],
]);

it('keeps the student competition page within a fixed number of queries whatever its rounds and registrations', function (bool $signedIn, int $max) {
    $competition = Competition::factory()->student()->create();
    $user = User::factory()->create();

    $seed = function (int $n) use ($competition): void {
        foreach (range(1, $n) as $i) {
            $round = new CompetitionRound(['name' => 'مرحلة '.$i, 'sort_order' => $i + 1, 'starts_at' => now()->addDays($i), 'ends_at' => now()->addDays($i)->addHours(3), 'question_count' => 10, 'duration_minutes' => 30]);
            $round->competition_id = $competition->id;
            $round->save();
        }

        CompetitionSubmission::factory()->count($n)->create(['competition_id' => $competition->id])
            ->each(fn (CompetitionSubmission $submission) => $submission->forceFill(['registered_at' => now()])->save());
    };

    expectQueryBudget($seed, fn () => $signedIn ? $this->actingAs($user)->get('/competitions/'.$competition->slug) : $this->get('/competitions/'.$competition->slug), $max);
})->with(['visitor' => [false, 3], 'signed in' => [true, 4]]);

it('keeps the competition form in the panel to a fixed number of queries whatever its prize table', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $competition = Competition::factory()->student()->create();

    // قفل الترتيب يُسأل مرة للسجل لا لكل حقل في كل صف من جدول الجوائز (كان 357 استعلاماً لمسابقة البراهين)
    expectQueryBudget(
        fn (int $n) => $competition->forceFill(['prize_table' => array_fill(0, $n, ['male' => 1000, 'female' => 1000])])->save(),
        fn () => $this->actingAs($admin)->get(CompetitionResource::getUrl('edit', ['record' => $competition])),
        19,
    );
});

it('still disables the locked fields of the competition form, each lock on its own', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));
    $open = Competition::factory()->student()->create(['prize_table' => [['male' => 1000, 'female' => 1000]]]);
    $schedule = Competition::factory()->student()->create(['prize_table' => [['male' => 1000, 'female' => 1000]]]);
    $schedule->forceFill(['results_approved_at' => now()])->save();
    $ranking = Competition::factory()->student()->create(['prize_table' => [['male' => 1000, 'female' => 1000]]]);
    $ranking->forceFill(['ranking_locked_at' => now()])->save();

    Livewire::test(EditCompetition::class, ['record' => $open->getRouteKey()])
        ->assertFormFieldEnabled('opens_at')->assertFormFieldEnabled('is_published')
        ->assertFormFieldEnabled('ranking_scope')->assertFormFieldEnabled('prize_table');

    Livewire::test(EditCompetition::class, ['record' => $schedule->getRouteKey()])
        ->assertFormFieldDisabled('opens_at')->assertFormFieldDisabled('closes_at')->assertFormFieldDisabled('is_published')
        ->assertFormFieldEnabled('ranking_scope');

    Livewire::test(EditCompetition::class, ['record' => $ranking->getRouteKey()])
        ->assertFormFieldEnabled('opens_at')
        ->assertFormFieldDisabled('ranking_scope')->assertFormFieldDisabled('prize_table');
});
