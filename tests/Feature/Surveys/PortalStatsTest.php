<?php

use App\Enums\RoleName;
use App\Filament\Widgets\PortalStatsWidget;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\Setting;
use App\Models\User;
use App\Support\PortalStats;
use Livewire\Livewire;

it('computes the portal statistics in the background and keeps the last result', function () {
    User::factory()->count(2)->create(['phone' => null, 'phone_verified_at' => null]);
    CompetitionSubmission::factory()->submitted()->count(3)->create();
    CompetitionSubmission::factory()->create();

    expect(PortalStats::cached())->toBeNull();

    $this->artisan('ejaz:refresh-stats')->assertSuccessful();
    $stats = PortalStats::cached();

    expect($stats['submissions'])->toBe(3)
        ->and($stats['drafts'])->toBe(1)
        ->and($stats['competitions'])->toBe(Competition::query()->published()->count())
        ->and($stats['users'])->toBe(User::count());
});

it('shows the statistics widget to the board and the super admin only', function () {
    $this->artisan('ejaz:refresh-stats');

    $this->actingAs(userWithRole(RoleName::BoardMember));
    expect(PortalStatsWidget::canView())->toBeTrue();
    Livewire::test(PortalStatsWidget::class)->assertSee(__('surveys.stats.submissions'));

    $this->actingAs(userWithRole(RoleName::SuperAdmin));
    expect(PortalStatsWidget::canView())->toBeTrue();

    foreach ([RoleName::ProgramManager, RoleName::ContentEditor, RoleName::Reviewer, RoleName::Secretary] as $role) {
        $this->actingAs(userWithRole($role));
        expect(PortalStatsWidget::canView())->toBeFalse();
    }
});

it('feeds a home statistic from live portal data, and keeps manual ones as entered', function () {
    CompetitionSubmission::factory()->submitted()->count(4)->create();
    Setting::put('home', ['show_stats' => true, 'stats' => [
        ['label' => 'مشاركة في المسابقات', 'source' => 'submissions', 'icon' => 'users'],
        approvedStat(),
        ['label' => 'مصدر غير مسموح', 'source' => 'drafts', 'icon' => 'users'],
    ]]);

    // قبل أول حساب لا تظهر الخانة الآلية، ولا رقم مخترع مكانها
    $this->get('/')->assertOk()->assertDontSee('مشاركة في المسابقات')->assertSee('مستفيد');

    $this->artisan('ejaz:refresh-stats');

    $this->get('/')->assertOk()->assertSee('مشاركة في المسابقات')->assertDontSee('مصدر غير مسموح');
});
