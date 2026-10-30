<?php

use App\Enums\RoleName;
use App\Support\PortalStats;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Vite;

// سمة لوحة الإدارة على التصور النهائي 13–15 (docs/decisions/2026-10-30-admin-theme.md)

const ADMIN_THEME = 'resources/css/filament/admin/theme.css';

it('registers the custom theme on the admin panel and in the build', function () {
    expect(Filament::getPanel('admin')->getViteTheme())->toBe(ADMIN_THEME);

    // السمة مبنية مع أصول الموقع، فيجدها Vite في manifest
    expect(Vite::asset(ADMIN_THEME))->toContain('/build/assets/theme-');
});

it('loads the panel with the theme and the white brand for every panel role', function (RoleName $role) {
    $this->actingAs(userWithRole($role))
        ->get('/admin')
        ->assertOk()
        ->assertSee(Vite::asset(ADMIN_THEME), false)
        ->assertSee('class="ej-sidebar-brand"', false)
        ->assertSee(asset('images/brand/logo-mark-white.png'), false)
        ->assertSee(asset('images/brand/logo-calligraphy-white.png'), false)
        ->assertSee('alt="'.__('admin.theme.logo_alt').'"', false);
})->with(fn (): array => array_map(fn (RoleName $role): array => [$role], RoleName::panelRoles()));

it('does not open the panel, theme or not, for an account without a panel role', function () {
    $this->actingAs(userWithRole(RoleName::Member))
        ->get('/admin')
        ->assertForbidden();
});

it('gives every portal stat an icon for its gradient tile', function () {
    $this->artisan('ejaz:refresh-stats')->assertSuccessful();
    expect(PortalStats::cached())->not->toBeNull();

    $this->actingAs(userWithRole(RoleName::ExecutiveDirector))
        ->get('/admin')
        ->assertOk()
        ->assertSee('fi-wi-stats-overview-stat-label-ctn', false)
        ->assertSeeInOrder([__('surveys.stats.groups.people'), 'fi-wi-stats-overview-stat-label-ctn', '<svg'], false);
});
