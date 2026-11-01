<?php

use App\Actions\Settings\UpdateHomeContent;
use App\Enums\RoleName;
use App\Filament\Pages\HomeContentPage;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\DailyVisit;
use App\Models\Setting;
use App\Models\User;
use App\Support\ContentCache;
use App\Support\HomeContent;
use App\Support\HomePortalStats;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
| «البوابة بالأرقام» العامة (البند H9 — docs/decisions/2026-11-01-site-stats-v2.md): مجاميع من الجداول القائمة، مخزنة في الخلفية،
| بمفتاح إظهار في «محتوى الرئيسية»، وبلا بيانات شخصية.
*/

function visitsOn(int $daysAgo, int $visitors, int $views = 0): void
{
    DailyVisit::query()->create([
        'date' => now(config('app.display_timezone'))->subDays($daysAgo)->toDateString(),
        'visitors' => $visitors,
        'views' => max($views, $visitors),
    ]);
}

function portalContent(User $actor, array $content): void
{
    app(UpdateHomeContent::class)->handle($actor, ['values' => ['items' => array_fill(0, HomeContent::VALUES, [])], ...$content]);
}

it('shows the visits, the competition registrants and the 30-day chart from the stored aggregates', function () {
    visitsOn(40, 5000);
    visitsOn(3, 700);
    visitsOn(0, 534);
    $competition = Competition::factory()->create();
    CompetitionSubmission::factory()->create(['competition_id' => $competition->id, 'registered_at' => now()]);
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => $competition->id]);

    $this->artisan('ejaz:refresh-stats')->assertSuccessful();

    $html = $this->get('/')->assertOk()
        ->assertSee('data-portal-stats', false)
        ->assertSee('data-portal-chart', false)
        ->assertSee(__('home.sections_final.headings.stats'))
        ->assertSee(__('home.sections_final.headings.stats_intro'))
        ->assertSee(__('home.portal_stats.visits'))
        ->assertSee(__('home.portal_stats.registrants'))
        ->assertSee(__('home.portal_stats.chart_title'))
        ->getContent();

    // الرقم كاملاً بفواصل الآلاف للعدّ التصاعدي وبلا سكربت، والرسم بمجموع آخر 30 يوماً وحده
    expect($html)->toContain('data-count-to="6234"')->toContain('6,234')
        ->and($html)->toContain('data-count-to="2"')
        ->and($html)->toContain(__('home.portal_stats.chart_label', ['total' => '1,234', 'max' => '700']));

    // «البوابة بالأرقام» آخراً قبل «شركاؤنا»
    expect(strpos($html, 'data-portal-stats'))->toBeGreaterThan(strpos($html, 'id="home-identity"'));
});

it('hides the section while there is nothing to show, and before the first background run', function () {
    // قبل أول حساب لا رقم ولا استعلام
    visitsOn(0, 10);
    $this->get('/')->assertOk()->assertDontSee('data-portal-stats', false);

    DailyVisit::query()->delete();
    ContentCache::flush();
    $this->artisan('ejaz:refresh-stats');
    $this->get('/')->assertOk()->assertDontSee('data-portal-stats', false);

    // الصفر لا يظهر بطاقة، ولا رسم لأيام بلا زيارة
    visitsOn(1, 0);
    $this->artisan('ejaz:refresh-stats');
    $this->get('/')->assertOk()->assertDontSee('data-portal-stats', false);
});

it('counts registrants in published competitions only, once each, without cancelled ones or untouched drafts', function () {
    $published = Competition::factory()->create();
    $student = CompetitionSubmission::factory()->create(['competition_id' => $published->id, 'registered_at' => now()]);
    // المسجل في مسابقتين منشورتين يُعدّ مرة
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => Competition::factory()->create()->id, 'user_id' => $student->user_id]);
    CompetitionSubmission::factory()->create(['competition_id' => $published->id]);
    CompetitionSubmission::factory()->create(['competition_id' => $published->id, 'registered_at' => now()])->delete();
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => Competition::factory()->unpublished()->create()->id]);

    expect(HomePortalStats::compute()['registrants'])->toBe(1);
});

it('keeps aggregates alone, with no personal data in the store or the page', function () {
    visitsOn(0, 12);
    $user = User::factory()->create(['name' => 'اسم شخصي للاختبار', 'email' => 'private-person@example.test']);
    CompetitionSubmission::factory()->create([
        'competition_id' => Competition::factory()->create()->id,
        'user_id' => $user->id,
        'registered_at' => now(),
        'full_name' => 'طالب سري للاختبار',
    ]);

    $this->artisan('ejaz:refresh-stats');

    expect(array_keys((array) HomePortalStats::cached()))->toBe(['visits', 'registrants', 'days'])
        ->and(HomePortalStats::cached()['days'])->each->toBeIn([null, 0, 12]);

    $this->get('/')->assertOk()->assertSee('data-portal-stats', false)
        ->assertDontSee('اسم شخصي للاختبار')->assertDontSee('private-person@example.test')->assertDontSee('طالب سري للاختبار');
});

it('reads the stored numbers alone on a request, with no aggregate queries', function () {
    visitsOn(0, 20);
    CompetitionSubmission::factory()->create(['competition_id' => Competition::factory()->create()->id, 'registered_at' => now()]);
    $this->artisan('ejaz:refresh-stats');
    ContentCache::flush();

    DB::enableQueryLog();
    $this->get('/')->assertOk()->assertSee('data-portal-stats', false);
    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $sql): bool => str_contains($sql, 'daily_visits') || str_contains($sql, 'competition_submissions')))->toBeEmpty();
});

it('refreshes the home cache when the numbers change while the section shows, and not when switched off', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    visitsOn(0, 5);
    $this->artisan('ejaz:refresh-stats');

    ContentCache::remember('home', fn (): string => 'cached');
    ContentCache::remember('governance.index', fn (): string => 'cached');

    // بلا تغيير لا مسح
    $this->artisan('ejaz:refresh-stats');
    expect(ContentCache::remember('home', fn (): string => 'fresh'))->toBe('cached');

    // رقم جديد: تُمسح الرئيسية وحدها
    DailyVisit::query()->update(['visitors' => 9]);
    $this->artisan('ejaz:refresh-stats');
    expect(ContentCache::remember('home', fn (): string => 'fresh'))->toBe('fresh')
        ->and(ContentCache::remember('governance.index', fn (): string => 'fresh'))->toBe('cached');

    // والقسم مطفأ: لا مسح ولو تغيّر الرقم
    portalContent($editor, ['portal_stats' => ['enabled' => false]]);
    ContentCache::remember('home', fn (): string => 'cached-off');
    DailyVisit::query()->update(['visitors' => 15]);
    $this->artisan('ejaz:refresh-stats');
    expect(ContentCache::remember('home', fn (): string => 'fresh'))->toBe('cached-off');
});

it('hides the automatic numbers with the switch in «محتوى الرئيسية», and keeps the approved ones from the settings', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    visitsOn(0, 30);
    $this->artisan('ejaz:refresh-stats');

    expect(HomeContent::showsPortalStats())->toBeTrue();

    portalContent($editor, ['portal_stats' => ['enabled' => false]]);

    expect(HomeContent::showsPortalStats())->toBeFalse()
        ->and(Setting::group(HomeContent::KEY)['portal_stats']['enabled'])->toBeFalse();
    $this->get('/')->assertOk()->assertDontSee('data-portal-stats', false)->assertDontSee(__('home.portal_stats.visits'));

    // والأرقام المعتمدة يدوياً بمفتاحها في «إعدادات الموقع»
    Setting::put('home', ['show_stats' => true, 'stats' => [approvedStat()]]);
    $this->get('/')->assertOk()->assertSee('data-portal-stats', false)->assertSee('مستفيد')
        ->assertDontSee(__('home.portal_stats.visits'))->assertDontSee('data-portal-chart', false);

    portalContent($editor, ['portal_stats' => ['enabled' => true], 'headings' => ['stats' => 'أرقامنا', 'stats_intro' => 'وصف من اللوحة']]);
    $this->get('/')->assertOk()->assertSee('أرقامنا')->assertSee('وصف من اللوحة')->assertSee(__('home.portal_stats.visits'))->assertSee('مستفيد');
});

it('shows the switch and the real default texts in «محتوى الرئيسية», and saves them', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    // الأصل في حقله لا اسم مفتاحه (home.sections_final)
    Livewire::test(HomeContentPage::class)
        ->assertFormSet([
            'portal_stats.enabled' => true,
            'headings.stats' => __('home.sections_final.headings.stats'),
            'headings.stats_intro' => __('home.sections_final.headings.stats_intro'),
        ])
        ->assertSee(__('settings.home_content_portal_stats.enabled'))
        ->fillForm(['portal_stats.enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(HomeContent::showsPortalStats())->toBeFalse()
        // حفظ الأصل كما هو لا يخزّنه نصاً
        ->and(Setting::group(HomeContent::KEY)['headings']['stats'] ?? null)->toBeNull();
});

it('refuses the switch from a user without the settings permission, or with a wrong value', function () {
    $user = User::factory()->create();

    expect(fn () => portalContent($user, ['portal_stats' => ['enabled' => false]]))->toThrow(AuthorizationException::class);

    expect(fn () => portalContent(userWithRole(RoleName::ContentEditor), ['portal_stats' => ['enabled' => 'نعم']]))->toThrow(ValidationException::class);
    expect(HomeContent::showsPortalStats())->toBeTrue();
});

it('draws the chart from the oldest day on the right to today on the left, without the days before counting began', function () {
    $days = array_fill(0, HomePortalStats::DAYS, null);
    $days[28] = 4;
    $days[29] = 8;

    $chart = HomePortalStats::chart($days);

    expect($chart['line'])->toBe('34.5,117 0,14')
        ->and($chart['area'])->toBe('34.5,220 34.5,117 0,14 0,220')
        ->and($chart['total'])->toBe(12)
        ->and($chart['max'])->toBe(8)
        ->and(HomePortalStats::chart(array_fill(0, HomePortalStats::DAYS, 0)))->toBeNull()
        ->and(HomePortalStats::chart(array_fill(0, HomePortalStats::DAYS, null)))->toBeNull();

    // يوم معدود واحد (أول يوم بعد النشر): نقطة لا تُرسم خطاً، فلا بطاقة رسم فارغة
    $first = array_fill(0, HomePortalStats::DAYS, null);
    $first[29] = 40;
    expect(HomePortalStats::chart($first))->toBeNull();
});

it('keeps the switch as it is when the content is saved without it', function () {
    $editor = userWithRole(RoleName::ContentEditor);

    portalContent($editor, ['portal_stats' => ['enabled' => false]]);
    portalContent($editor, ['headings' => ['stats' => 'عنوان آخر']]);

    expect(HomeContent::showsPortalStats())->toBeFalse()
        ->and(HomeContent::text('headings', 'stats'))->toBe('عنوان آخر');
});
