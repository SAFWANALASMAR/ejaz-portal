<?php

use App\Enums\RoleName;
use App\Support\PortalStats;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Vite;

// سمة لوحة الإدارة على التصور النهائي 13–15 (docs/decisions/2026-10-30-admin-theme.md)

const ADMIN_THEME = 'resources/css/filament/admin/theme.css';

// أصول البناء لا خادم Vite (public/hot حين تعمل حاويته)، كالإنتاج
beforeEach(fn () => Vite::useHotFile(storage_path('framework/testing/no-vite-hot')));

it('registers the custom theme on the admin panel and in the build', function () {
    expect(Filament::getPanel('admin')->getViteTheme())->toBe(ADMIN_THEME);

    // السمة مبنية مع أصول الموقع، فيجدها Vite في manifest
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
    expect($manifest)->toHaveKey(ADMIN_THEME)
        ->and(Vite::asset(ADMIN_THEME))->toContain('/build/assets/theme-');
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

it('gives every portal stat an icon for its gradient tile', function () {
    $this->artisan('ejaz:refresh-stats')->assertSuccessful();
    expect(PortalStats::cached())->not->toBeNull();

    $html = $this->actingAs(userWithRole(RoleName::ExecutiveDirector))->get('/admin')->assertOk()->getContent();

    // أيقونة (svg) قبل تسمية الإحصاء مباشرة، في المجموعات الأربع
    foreach (['users', 'requests', 'programs', 'members'] as $key) {
        expect($html)->toMatch('#</svg>\s*<span class="fi-wi-stats-overview-stat-label">\s*'.preg_quote(__('surveys.stats.'.$key), '#').'\s*</span>#u');
    }
});
