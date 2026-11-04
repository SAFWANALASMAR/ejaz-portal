<?php

use App\Enums\RoleName;
use App\Filament\Resources\RegisterEntries\RegisterEntryResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\PeopleStatsWidget;
use App\Filament\Widgets\PortalWidget;
use App\Filament\Widgets\ProgramStatsByTypeWidget;
use App\Filament\Widgets\RegistersOverviewWidget;
use App\Filament\Widgets\VisitsChartWidget;
use App\Support\ProgramStatsByType;
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

it('draws the empty state with the first menu groups of the account as buttons, only for a role with nothing on its dashboard', function () {
    // محرر المحتوى بلا بوابة ولا إحصائيات: أزرار لأول مجموعات قائمته، و«القائمة» لا «القائمة الجانبية» (ملاحظة المقيّم على #168)
    $html = $this->actingAs(userWithRole(RoleName::ContentEditor))
        ->get('/admin')
        ->assertOk()
        ->assertSee('data-ej-dashboard-empty', false)
        ->assertSee(__('admin.dashboard.empty_heading'))
        ->assertSee(__('admin.dashboard.empty_body'))
        ->assertSee(__('admin.groups.content'))
        ->getContent();

    $section = (string) strstr((string) strstr($html, 'data-ej-dashboard-empty'), '</div>', true);
    expect($section)->not->toContain('القائمة الجانبية')
        ->and(substr_count($section, 'data-ej-empty-link'))->toBeGreaterThan(0)->toBeLessThanOrEqual(4)
        // لا زر لشاشة لا يملكها: المستخدمون للمشرفين وحدهم
        ->and($section)->not->toContain(UserResource::getUrl());

    $this->actingAs(userWithRole(RoleName::ExecutiveDirector))
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('data-ej-dashboard-empty', false);
});

it('shows each portal capability as a card with its icon in a grid sized to the count, the general stats linked to their section', function () {
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector));

    $html = Livewire::test(PortalWidget::class)->html();

    // خمس قدرات للمدير التنفيذي بمربع أيقونة لكل منها، ثلاث في السطر فلا تبقى بطاقة وحيدة، و«الإحصائيات العامة» إلى قسمها
    expect(substr_count($html, 'data-ej-portal-item'))->toBe(5)
        ->and(substr_count($html, 'data-ej-portal-icon'))->toBe(5)
        ->and($html)->toContain('data-ej-portal-columns="3"')
        ->and($html)->toContain('href="#'.PeopleStatsWidget::ANCHOR.'"')
        ->and($html)->toContain(__('portals.executive.items.stats'));

    // والقسم نفسه يحمل المرساة
    expect(Livewire::test(PeopleStatsWidget::class)->html())->toContain('id="'.PeopleStatsWidget::ANCHOR.'"');

    // المحكّم قدرة واحدة في عمود واحد
    $this->actingAs(userWithRole(RoleName::Reviewer));
    expect(Livewire::test(PortalWidget::class)->html())->toContain('data-ej-portal-columns="1"');
});

it('draws the empty documents box with a link to the register for whoever sees it', function () {
    // السكرتير: صندوق «المستندات ذات الصلاحية» وحده في رئيسيته (ملاحظة المقيّم على #168)
    $this->actingAs(userWithRole(RoleName::Secretary));

    Livewire::test(RegistersOverviewWidget::class)
        ->assertSeeHtml('data-ej-registers-empty')
        ->assertSee(__('registers.widget.none'))
        ->assertSee(__('admin.dashboard.registers_link'))
        ->assertSeeHtml('href="'.RegisterEntryResource::getUrl().'"');
});

it('draws an empty visits chart as no data yet, without the cross icon', function () {
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector));

    Livewire::test(VisitsChartWidget::class)
        ->assertSeeHtml('data-ej-chart-empty')
        ->assertSee(__('admin.dashboard.no_data'))
        ->assertSee(__('surveys.stats.visits_chart.empty'))
        ->assertDontSeeHtml('fi-empty-state-icon');
});

it('draws the program stats as empty when every type is zero, without empty axes or a paragraph of zeros', function () {
    $this->artisan('ejaz:refresh-stats')->assertSuccessful();
    expect(ProgramStatsByType::cached())->not->toBeEmpty();
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector));

    Livewire::test(ProgramStatsByTypeWidget::class)
        ->assertSeeHtml('data-ej-chart-empty')
        ->assertSee(__('program_stats.summary_none'))
        ->assertDontSeeHtml('data-program-stats-row');
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
    $allowed = ['#e3f1fc', '#f2f8fd', '#cfe3f4', '#2e6898', '#1f4565', '#142e45', '#ffffff', '#fff', '255 255 255', '142 197 240', '31 69 101'];
    preg_match_all('/#[0-9a-f]{3,8}\b|rgb\(\s*([0-9 ]+?)\s*(?:\/|\))/i', (string) preg_replace('#/\*.*?\*/#s', '', $css), $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty();
    foreach ($matches as $match) {
        $colour = strtolower($match[1] ?? '') !== '' ? trim(preg_replace('/\s+/', ' ', $match[1])) : strtolower($match[0]);
        expect($allowed)->toContain($colour);
    }
});
