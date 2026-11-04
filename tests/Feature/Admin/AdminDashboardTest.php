<?php

use App\Enums\RoleName;
use App\Filament\Widgets\PortalWidget;
use Illuminate\Support\Facades\Vite;
use Livewire\Livewire;

// رئيسيات اللوحة والبوابات (طلب لوحة الإدارة، 4 نوفمبر 2026 — الدفعة ٢، resources/css/filament/admin/dashboard.css).
// الشكل نفسه (الرقم البارز، والمربعات المتدرجة، والتباين، ولا تمرير أفقي) في اختبار المتصفح tests/browser/admin-dashboards.spec.mjs

beforeEach(fn () => Vite::useHotFile(storage_path('framework/testing/no-vite-hot')));

it('greets every panel role by name with its role labels in the dashboard header', function (RoleName $role) {
    $user = userWithRole($role);

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertSee('data-ej-dashboard-hero', false)
        ->assertSee(__('admin.dashboard.greeting', ['name' => $user->name]))
        ->assertSee(__('admin.dashboard.description'))
        ->assertSee($role->getLabel())
        // عنصر «مرحباً» الصغير صار تحية الرأس
        ->assertDontSee('fi-account-widget', false);
})->with(fn (): array => array_map(fn (RoleName $role): array => [$role], RoleName::panelRoles()));

it('draws the empty state only for a role with nothing on its dashboard', function () {
    // محرر المحتوى بلا بوابة ولا إحصائيات
    $this->actingAs(userWithRole(RoleName::ContentEditor))
        ->get('/admin')
        ->assertOk()
        ->assertSee('data-ej-dashboard-empty', false)
        ->assertSee(__('admin.dashboard.empty_heading'));

    $this->actingAs(userWithRole(RoleName::ExecutiveDirector))
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('data-ej-dashboard-empty', false);
});

it('shows each portal capability as a card with its icon, linked only when the role may open it', function () {
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector));

    $html = Livewire::test(PortalWidget::class)->html();

    // خمس قدرات للمدير التنفيذي، لكل منها مربع أيقونة، و«الإحصائيات العامة» في الصفحة نفسها بلا رابط
    expect(substr_count($html, 'data-ej-portal-item'))->toBe(5)
        ->and(substr_count($html, 'data-ej-portal-icon'))->toBe(5)
        ->and($html)->toContain('ej-portal-card-static')
        ->and($html)->toContain(__('portals.executive.items.stats'));
});

it('keeps the dashboard header away from visitors and beneficiaries', function () {
    $this->get('/admin')->assertRedirect();

    $this->actingAs(userWithRole(RoleName::Member))
        ->get('/admin')
        ->assertForbidden()
        ->assertDontSee('data-ej-dashboard-hero', false);
});

it('imports the dashboard styles into the admin theme with blue colours only', function () {
    $theme = (string) file_get_contents(resource_path('css/filament/admin/theme.css'));
    $css = (string) file_get_contents(resource_path('css/filament/admin/dashboard.css'));

    expect($theme)->toContain("@import './dashboard.css';");

    // الأزرق وحده (حدود الطلب، وأمر التصور النهائي البند G1): كل لون صريح في الملف من درجات الهوية الزرقاء أو الأبيض
    $allowed = ['#e3f1fc', '#f2f8fd', '#cfe3f4', '#1f4565', '#142e45', '#ffffff', '#fff', '255 255 255', '142 197 240', '31 69 101'];
    preg_match_all('/#[0-9a-f]{3,8}\b|rgb\(\s*([0-9 ]+?)\s*(?:\/|\))/i', (string) preg_replace('#/\*.*?\*/#s', '', $css), $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty();
    foreach ($matches as $match) {
        $colour = strtolower($match[1] ?? '') !== '' ? trim(preg_replace('/\s+/', ' ', $match[1])) : strtolower($match[0]);
        expect($allowed)->toContain($colour);
    }
});
