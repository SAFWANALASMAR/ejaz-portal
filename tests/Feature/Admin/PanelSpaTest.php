<?php

use App\Console\Commands\CreateDemoUser;
use App\Enums\RoleName;
use App\Http\Middleware\AdminIdleTimeout;
use App\Models\FormTemplate;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;

// طلب تنقّل داخل اللوحة كما يرسله Livewire عند النقر على رابط wire:navigate
function navigateHeaders(): array
{
    return ['X-Livewire-Navigate' => '1'];
}

it('turns on SPA mode for the admin panel', function () {
    expect(Filament::getPanel('admin')->hasSpaMode())->toBeTrue();

    $this->actingAs(userWithRole(RoleName::TechnicalAdmin))
        ->get('/admin/users')
        ->assertOk()
        ->assertSee('href="'.url('/admin/menu-items').'" wire:navigate', false);
});

it('loads every link outside the panel fully, and keeps panel links in SPA', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin))->get('/admin')->assertOk();

    // روابط اللوحة نفسها
    expect(FilamentView::hasSpaMode(url('/admin/users')))->toBeTrue()
        ->and(FilamentView::hasSpaMode(url('/admin/users?page=2')))->toBeTrue()
        ->and(FilamentView::hasSpaMode(url('/admin')))->toBeTrue();

    // الموقع العام، والدخول والخروج والتحقق الثنائي، وتنزيل التصدير والملفات والطباعة
    foreach ([
        url('/'),
        url('/?ref=admin'),
        url('/login'),
        url('/logout'),
        url('/two-factor-challenge'),
        route('account.security'),
        url('/filament/exports/1/download?format=xlsx'),
        url('/job-files/documents/1/download?expires=1&signature=x'),
        url('/documents/entries/0f4c/print'),
        url('/assembly/files/1'),
        url('/admin-files/registers/1/0?signature=x'),
        url('/governance/charter/download'),
        url('/storage/media/photo.jpg'),
        url('/about#board'),
        '/login',
        '/',
        '/governance?type=policies',
    ] as $url) {
        expect(FilamentView::hasSpaMode($url))->toBeFalse($url);
    }
});

it('leaves the panel with a full page load when the idle limit ends a navigation', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);

    $response = $this->actingAs($admin)
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes(31)->getTimestamp()])
        ->get('/admin/users', navigateHeaders());

    // صفحة تحمّل صفحة الدخول تحميلاً كاملاً، لا تحويل يضعها Livewire داخل قالب اللوحة
    $response->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.route('login').'">', false)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSessionHas('status', __('auth.idle_logout'));
    $this->assertGuest();
});

it('keeps the plain redirect outside SPA navigation', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin))
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes(31)->getTimestamp()])
        ->get('/admin/users')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('keeps navigating inside the panel while the administrator is active', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin))
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes(5)->getTimestamp()])
        ->get('/admin/users', navigateHeaders())
        ->assertOk()
        ->assertDontSee('http-equiv="refresh"', false);

    $this->assertAuthenticated();
});

it('sends a sensitive role without two-factor to the security page with a full load', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::TechnicalAdmin->value);

    $this->actingAs($user)
        ->get('/admin/users', navigateHeaders())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.route('account.security').'">', false)
        ->assertSessionHas('status', __('auth.two_factor_required'));
});

it('shows the refusal of a deactivated administrator with a full load', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $admin->forceFill(['is_active' => false])->save();

    // اللوحة ترفضه (PanelAccessTest)، فتُحمَّل صفحة الرفض نفسها تحميلاً كاملاً لا داخل قالب اللوحة
    $this->actingAs($admin)
        ->get('/admin/users', navigateHeaders())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.url('/admin/users').'">', false);
});

it('reloads an error page fully instead of placing it inside the panel', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $url = url('/admin/users/999999/edit');

    // سجل غير موجود (حذفه زميل مثلاً): الصفحة نفسها بتحميل كامل، فيعرض الخادم خطأها بقالبه
    $this->actingAs($admin)
        ->get($url, navigateHeaders())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.$url.'">', false);

    $this->actingAs($admin)->get($url)->assertNotFound();
});

it('sends a guest whose session ended to the login page with a full load', function () {
    $this->get('/admin/users', navigateHeaders())
        ->assertOk()
        ->assertSee('<meta http-equiv="refresh" content="0;url='.route('login').'">', false);
});

it('creates a local super admin with two-factor for the panel browser test', function () {
    $this->artisan('ejaz:demo-user', ['--admin' => true, '--json' => true])->assertSuccessful();

    $admin = User::query()->where('email', 'demo.admin@ejaz.test')->sole();

    expect($admin->hasRole(RoleName::TechnicalAdmin->value))->toBeTrue()
        ->and($admin->hasEnabledTwoFactorAuthentication())->toBeTrue();

    // نموذج بحقل توقيع واحد، ولا يتكرر بإعادة التشغيل
    $this->artisan('ejaz:demo-user', ['--admin' => true])->assertSuccessful();
    $template = FormTemplate::query()->where('title', CreateDemoUser::DEMO_FORM_TITLE)->sole();

    expect(collect($template->currentVersion->fields)->pluck('type')->all())->toContain('signature');
});

it('refuses the demo admin outside the local environment', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ejaz:demo-user', ['--admin' => true])->assertFailed();

    expect(User::query()->where('email', 'demo.admin@ejaz.test')->exists())->toBeFalse();
});
