<?php

use App\Enums\RoleName;
use App\Filament\Widgets\AnnualPlanWidget;
use App\Filament\Widgets\PeopleStatsWidget;
use App\Filament\Widgets\RegistersOverviewWidget;
use App\Models\User;
use Livewire\Livewire;

// عناوين عناصر رئيسية اللوحة بمربع أيقونة بدل الشريط، وصندوق المستندات ذات الصلاحية بتدرج أزرق
// (البندان N2 وG2 من تدقيق القبول، 2 نوفمبر). والمربع والتدرج في resources/css/filament/admin/theme.css

/**
 * رأس القسم يبدأ بأيقونة (svg بصنف fi-icon) قبل العنوان.
 */
function headingWithTile(string $heading): string
{
    return '#<header\s+class="fi-section-header"\s*>\s*<svg class="fi-icon[^"]*"[^>]*>(?:(?!</header>).)*?</svg>(?:(?!</header>).)*?<h2[^>]*class="fi-section-header-heading"[^>]*>\s*'.preg_quote(e($heading), '#').'\s*</h2>#su';
}

it('heads the three dashboard sections with an icon tile', function () {
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector));

    expect(Livewire::test(RegistersOverviewWidget::class)->assertOk()->html())->toMatch(headingWithTile(__('registers.widget.heading')))
        ->and(Livewire::test(AnnualPlanWidget::class)->assertOk()->html())
        ->toMatch(headingWithTile(__('registers.plan.widget_heading', ['year' => (int) now(config('app.display_timezone'))->year])))
        ->and(Livewire::test(PeopleStatsWidget::class)->assertOk()->html())->toMatch(headingWithTile(__('surveys.stats.groups.people')));
});

it('gives the documents box its light blue gradient class', function () {
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector));

    expect(Livewire::test(RegistersOverviewWidget::class)->html())->toMatch('#<section[^>]*class="[^"]*\bfi-section\b[^"]*\bej-registers-box\b#');
});

it('keeps the widgets hidden from users without access', function () {
    $this->actingAs(User::factory()->create());

    expect(RegistersOverviewWidget::canView())->toBeFalse()
        ->and(AnnualPlanWidget::canView())->toBeFalse()
        ->and(PeopleStatsWidget::canView())->toBeFalse();
});
