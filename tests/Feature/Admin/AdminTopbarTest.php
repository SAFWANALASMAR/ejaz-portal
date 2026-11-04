<?php

use App\Enums\RoleName;
use Illuminate\Support\Facades\Vite;

// الشريط العلوي والجانبي في لوحة الإدارة (طلب لوحة الإدارة، 4 نوفمبر 2026 — الدفعة ١، resources/css/filament/admin/shell.css).
// الشكل نفسه (التوسيط في المصغَّر، والكبسولة، وعدم القص) في اختبار المتصفح tests/browser/admin-topbar.spec.mjs

beforeEach(fn () => Vite::useHotFile(storage_path('framework/testing/no-vite-hot')));

it('shows the site identity in the topbar and a close button in the mobile sidebar for every panel role', function (RoleName $role) {
    $this->actingAs(userWithRole($role))
        ->get('/admin')
        ->assertOk()
        // رمز الشعار والمخطوطة بالأبيض في الشريط العلوي، والرابط باسم الجمعية
        ->assertSee('data-ej-topbar-brand aria-label="'.__('admin.theme.logo_alt').'"', false)
        ->assertSee('data-ej-brand-mark', false)
        ->assertSee('data-ej-brand-calligraphy', false)
        // زر إغلاق القائمة المنسدلة على الجوال باسمه العربي
        ->assertSee('data-ej-sidebar-close', false)
        ->assertSee('aria-label="'.__('filament-panels::layout.actions.sidebar.collapse.label').'"', false);
})->with(fn (): array => array_map(fn (RoleName $role): array => [$role], RoleName::panelRoles()));

it('keeps the panel and its topbar away from visitors and beneficiaries', function () {
    $this->get('/admin')->assertRedirect();

    $this->actingAs(userWithRole(RoleName::Member))
        ->get('/admin')
        ->assertForbidden()
        ->assertDontSee('data-ej-topbar-brand', false);
});

it('imports the topbar and sidebar styles into the admin theme with blue colours only', function () {
    $theme = (string) file_get_contents(resource_path('css/filament/admin/theme.css'));
    $shell = (string) file_get_contents(resource_path('css/filament/admin/shell.css'));

    expect($theme)->toContain("@import './shell.css';");

    // الأزرق وحده (حدود الطلب، وأمر التصور النهائي البند G1): كل لون صريح في الملف من درجات الهوية الزرقاء أو الأبيض
    $allowed = ['#17344f', '#1f4565', '#ffffff', '#fff', '255 255 255', '142 197 240', '15 37 54'];
    preg_match_all('/#[0-9a-f]{3,8}\b|rgb\(\s*([0-9 ]+?)\s*(?:\/|\))/i', (string) preg_replace('#/\*.*?\*/|url\("data:[^"]*"\)#s', '', $shell), $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty();
    foreach ($matches as $match) {
        $colour = strtolower($match[1] ?? '') !== '' ? trim(preg_replace('/\s+/', ' ', $match[1])) : strtolower($match[0]);
        expect($allowed)->toContain($colour);
    }
});
