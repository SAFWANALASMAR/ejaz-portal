<?php

use App\Enums\MeetingBody;
use App\Enums\MembershipStatus;
use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Filament\Pages\SiteSettings;
use App\Filament\Widgets\AssemblyStatsWidget;
use App\Filament\Widgets\PeopleStatsWidget;
use App\Filament\Widgets\ProgramsStatsWidget;
use App\Filament\Widgets\RequestsStatsWidget;
use App\Filament\Widgets\VisitsChartWidget;
use App\Models\ArchiveItem;
use App\Models\AssemblyMembership;
use App\Models\DailyVisit;
use App\Models\FormEntry;
use App\Models\Meeting;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Support\PortalStats;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * عناصر لوحة الإحصائيات كلها: المجموعات الأربع ورسم الزيارات.
 *
 * @return list<class-string>
 */
function statsWidgets(): array
{
    return [PeopleStatsWidget::class, VisitsChartWidget::class, RequestsStatsWidget::class, ProgramsStatsWidget::class, AssemblyStatsWidget::class];
}

/**
 * بيانات رسم الزيارات كما يرسلها العنصر إلى Chart.js.
 *
 * @return array<string, mixed>
 */
function visitsChartData(): array
{
    return (fn (): array => $this->getCachedData())->call(Livewire::test(VisitsChartWidget::class)->instance());
}

/**
 * أرقام مجموعة كما تعرضها: الوصف ثم الرقم.
 *
 * @param  class-string  $widget
 * @return array<string, string>
 */
function statsShown(string $widget): array
{
    return collect((fn (): array => $this->getStats())->call(Livewire::test($widget)->instance()))
        ->mapWithKeys(fn (Stat $stat): array => [(string) $stat->getLabel() => (string) $stat->getValue()])
        ->all();
}

it('counts the submitted requests by status, without the drafts', function () {
    ServiceRequest::factory()->count(2)->create();
    ServiceRequest::factory()->status(ServiceRequestStatus::UnderReview)->create();
    ServiceRequest::factory()->status(ServiceRequestStatus::NeedsCompletion)->create();
    ServiceRequest::factory()->status(ServiceRequestStatus::Accepted)->count(3)->create();
    ServiceRequest::factory()->status(ServiceRequestStatus::Rejected)->create();
    ServiceRequest::factory()->status(ServiceRequestStatus::Closed)->create();
    ServiceRequest::factory()->draft()->count(4)->create();
    ServiceRequest::factory()->create()->delete();

    $stats = PortalStats::compute();

    expect($stats['requests'])->toBe(9)
        ->and($stats['requests_received'])->toBe(2)
        ->and($stats['requests_under_review'])->toBe(1)
        ->and($stats['requests_needs_completion'])->toBe(1)
        ->and($stats['requests_accepted'])->toBe(3)
        ->and($stats['requests_rejected'])->toBe(1)
        ->and($stats['requests_closed'])->toBe(1)
        ->and($stats)->not->toHaveKey('requests_draft');
});

it('counts the published programs and the registrations of programs that were not deleted', function () {
    $program = Program::factory()->create();
    Program::factory()->unpublished()->create();
    Program::factory()->create()->delete();

    ProgramRegistration::factory()->count(3)->for($program)->create();
    ProgramRegistration::factory()->for($program)->create(['attended_at' => now()]);
    ProgramRegistration::factory()->waitlisted()->count(2)->for($program)->create();
    ProgramRegistration::factory()->cancelled()->for($program)->create();

    $deleted = Program::factory()->create();
    ProgramRegistration::factory()->count(5)->for($deleted)->create(['attended_at' => now()]);
    $deleted->delete();

    $stats = PortalStats::compute();

    expect($stats['programs'])->toBe(1)
        ->and($stats['registrations'])->toBe(4)
        ->and($stats['waitlisted'])->toBe(2)
        ->and($stats['attended'])->toBe(1);
});

it('counts the active assembly members, its meetings, the filled forms and the archive', function () {
    AssemblyMembership::factory()->count(2)->create();
    AssemblyMembership::factory()->status(MembershipStatus::Suspended)->create();
    AssemblyMembership::factory()->status(MembershipStatus::Ended)->create();
    AssemblyMembership::factory()->create()->user->forceFill(['is_active' => false])->save();

    Meeting::factory()->count(2)->create();
    Meeting::factory()->create(['starts_at' => now()->subDay()]);
    Meeting::factory()->held()->create();
    Meeting::factory()->cancelled()->create();
    Meeting::factory()->create(['body' => MeetingBody::Board]);
    Meeting::factory()->held()->create(['body' => MeetingBody::Board]);

    FormEntry::factory()->count(2)->create();
    FormEntry::factory()->approved()->count(3)->create();
    FormEntry::factory()->create()->delete();

    ArchiveItem::factory()->count(2)->create();
    ArchiveItem::factory()->create()->delete();

    $stats = PortalStats::compute();

    expect($stats['members'])->toBe(2)
        ->and($stats['upcoming_meetings'])->toBe(2)
        ->and($stats['held_meetings'])->toBe(1)
        ->and($stats['form_drafts'])->toBe(2)
        ->and($stats['form_approved'])->toBe(3)
        ->and($stats['archive_items'])->toBe(2);
});

it('takes the visits of today in Riyadh from what the command moved to the database', function () {
    // الواحدة فجراً في الرياض، والعاشرة مساء أمس بتوقيت UTC: اليوم يومان مختلفان
    $this->travelTo(Carbon::parse('2026-10-18 01:00', 'Asia/Riyadh'));
    DailyVisit::query()->create(['date' => '2026-10-17', 'views' => 50, 'visitors' => 20]);
    DailyVisit::query()->create(['date' => '2026-10-18', 'views' => 7, 'visitors' => 3]);

    expect(PortalStats::compute())->toMatchArray(['views_today' => 7, 'visitors_today' => 3]);
});

it('shows every computed number, with its label, in exactly one group', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));
    $this->artisan('ejaz:refresh-stats')->assertSuccessful();

    $keys = array_keys(PortalStats::cached());
    $shown = collect([PeopleStatsWidget::class, RequestsStatsWidget::class, ProgramsStatsWidget::class, AssemblyStatsWidget::class])
        ->flatMap(fn (string $widget): array => (fn (): array => $this->keys())->call(new $widget));

    expect($shown->all())->toEqualCanonicalizing($keys)
        ->and($shown->duplicates()->all())->toBe([]);

    foreach ($keys as $key) {
        expect(__('surveys.stats.'.$key))->not->toBe('surveys.stats.'.$key);
    }
});

it('shows the statistics dashboard to the board, the administrators and the super admin only', function () {
    $this->artisan('ejaz:refresh-stats');

    foreach ([RoleName::BoardMember, RoleName::ExecutiveDirector, RoleName::TechnicalAdmin, RoleName::SuperAdmin] as $role) {
        $this->actingAs(userWithRole($role));

        $dashboard = $this->get('/admin')->assertOk();

        foreach (statsWidgets() as $widget) {
            expect($widget::canView())->toBeTrue();
            $dashboard->assertSeeLivewire($widget);
        }
    }

    foreach (array_filter(RoleName::cases(), fn (RoleName $role): bool => ! in_array($role, [RoleName::BoardMember, RoleName::ExecutiveDirector, RoleName::TechnicalAdmin, RoleName::SuperAdmin], true)) as $role) {
        $this->actingAs(userWithRole($role));

        foreach (statsWidgets() as $widget) {
            expect($widget::canView())->toBeFalse();
        }
    }

    // من يدخل اللوحة بلا stats.view لا يرى العناصر، ولا يفتحها مباشرة
    $this->actingAs(userWithRole(RoleName::Secretary));
    $dashboard = $this->get('/admin')->assertOk();

    foreach (statsWidgets() as $widget) {
        $dashboard->assertDontSeeLivewire($widget);
        Livewire::test($widget)->assertForbidden();
    }
});

it('shows the numbers of each group from the stored computation, not from live data', function () {
    $this->actingAs(userWithRole(RoleName::BoardMember));
    ServiceRequest::factory()->count(12)->create();
    $this->artisan('ejaz:refresh-stats');

    // بعد الحساب لا يتغير المعروض حتى الحساب التالي
    ServiceRequest::factory()->count(5)->create();

    expect(statsShown(RequestsStatsWidget::class))->toMatchArray([
        __('surveys.stats.requests') => '12',
        __('surveys.stats.requests_received') => '12',
        __('surveys.stats.requests_accepted') => '0',
    ]);

    $this->artisan('ejaz:refresh-stats');

    expect(statsShown(RequestsStatsWidget::class))->toMatchArray([__('surveys.stats.requests') => '17']);
    Livewire::test(RequestsStatsWidget::class)->assertSeeInOrder([__('surveys.stats.groups.requests'), __('surveys.stats.requests'), '17']);
});

it('shows large numbers with thousands separators', function () {
    $this->actingAs(userWithRole(RoleName::BoardMember));
    DailyVisit::query()->create(['date' => now(config('app.display_timezone'))->toDateString(), 'views' => 12345, 'visitors' => 6789]);
    $this->artisan('ejaz:refresh-stats');

    expect(statsShown(PeopleStatsWidget::class))->toMatchArray([
        __('surveys.stats.views_today') => '12,345',
        __('surveys.stats.visitors_today') => '6,789',
    ]);
});

it('shows the first group before the first computation to say it is coming, and hides the others', function () {
    $this->actingAs(userWithRole(RoleName::BoardMember));

    expect(PeopleStatsWidget::canView())->toBeTrue()
        ->and(RequestsStatsWidget::canView())->toBeFalse()
        ->and(ProgramsStatsWidget::canView())->toBeFalse()
        ->and(AssemblyStatsWidget::canView())->toBeFalse();
    Livewire::test(PeopleStatsWidget::class)->assertSee(__('surveys.stats.not_ready'));

    $this->artisan('ejaz:refresh-stats');

    expect(RequestsStatsWidget::canView())->toBeTrue();
    Livewire::test(PeopleStatsWidget::class)->assertDontSee(__('surveys.stats.not_ready'))->assertSee(__('surveys.stats.updated', ['time' => '']));
});

it('draws the visits of the last 30 days day by day, without numbers before the first counted day', function () {
    $this->actingAs(userWithRole(RoleName::BoardMember));
    // الواحدة فجراً في الرياض، وما زال 17 أكتوبر بتوقيت UTC: الأيام الثلاثون أيام الرياض
    $this->travelTo(Carbon::parse('2026-10-18 01:00', 'Asia/Riyadh'));
    DailyVisit::query()->create(['date' => '2026-10-10', 'views' => 40, 'visitors' => 12]);
    DailyVisit::query()->create(['date' => '2026-10-12', 'views' => 25, 'visitors' => 9]);
    DailyVisit::query()->create(['date' => '2026-10-18', 'views' => 6, 'visitors' => 2]);
    DailyVisit::query()->create(['date' => '2026-09-01', 'views' => 999, 'visitors' => 999]);

    $data = visitsChartData();
    [$views, $visitors] = [$data['datasets'][0]['data'], $data['datasets'][1]['data']];

    // من 19 سبتمبر إلى اليوم، وأول يوم معدود قبلها فالأيام بلا زيارات صفر
    expect($data['labels'])->toHaveCount(30)
        ->and($data['labels'][0])->toBe('19 '.__('dates.months.9'))
        ->and($data['labels'][29])->toBe('18 '.__('dates.months.10'))
        ->and($views[21])->toBe(40)
        ->and($views[22])->toBe(0)
        ->and($views[23])->toBe(25)
        ->and($views[29])->toBe(6)
        ->and($visitors[29])->toBe(2)
        ->and(array_sum($views))->toBe(71);

    // وقبل أول يوم معدود لا رقم: العدّاد لم يكن موجوداً
    DailyVisit::query()->where('date', '2026-09-01')->delete();
    [$views] = [visitsChartData()['datasets'][0]['data']];

    expect(array_slice($views, 0, 21))->each->toBeNull()
        ->and($views[21])->toBe(40)
        ->and($views[22])->toBe(0);

    Livewire::test(VisitsChartWidget::class)->assertSee(__('surveys.stats.visits_chart.summary', ['days' => 30, 'views' => '71', 'day' => '10 '.__('dates.months.10'), 'max' => '40']));
});

it('shows an empty chart before any counted visit', function () {
    $this->actingAs(userWithRole(RoleName::BoardMember));

    expect(visitsChartData())->toBe([]);
    Livewire::test(VisitsChartWidget::class)->assertSee(__('surveys.stats.visits_chart.empty'));
});

it('feeds the home page from the published programs and the confirmed registrations', function () {
    $program = Program::factory()->create();
    Program::factory()->create();
    ProgramRegistration::factory()->count(3)->for($program)->create();
    ProgramRegistration::factory()->waitlisted()->for($program)->create();

    $this->actingAs(userWithRole(RoleName::ContentEditor));
    Livewire::test(SiteSettings::class)
        ->fillForm(['home.show_stats' => true, 'home.stats' => [
            ['label' => 'برنامج منشور', 'source' => 'programs', 'icon' => 'users'],
            ['label' => 'مستفيد من البرامج', 'source' => 'registrations', 'icon' => 'users'],
        ]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(collect(Setting::group('home')['stats'])->pluck('source')->all())->toBe(['programs', 'registrations']);

    // قبل أول حساب لا تظهر الخانتان، ثم تظهران بأرقام البوابة
    $this->get('/')->assertOk()->assertDontSee('برنامج منشور')->assertDontSee('مستفيد من البرامج');

    $this->artisan('ejaz:refresh-stats');

    // كل رقم في عنصره بعد وصفه (الوصف قبل الرقم في الصفحة، ويقلبهما الترتيب على الشاشة): برنامجان منشوران،
    // وثلاثة تسجيلات مؤكدة بلا قائمة الانتظار
    $this->get('/')->assertOk()->assertSeeInOrder(['برنامج منشور', '>2</span>', 'مستفيد من البرامج', '>3</span>'], false);
});
