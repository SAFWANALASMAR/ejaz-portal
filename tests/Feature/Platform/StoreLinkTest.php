<?php

use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\Setting;
use App\Rules\StoreUrl;
use Database\Seeders\MenuSeeder;
use Livewire\Livewire;

/**
 * قرار صفوان (10 أكتوبر 2026): المتجر المعتمد على سلة https://ejaz.org.sa، والدفع الداخلي مؤرشف خلف
 * مفتاح معطّل. docs/plans/store-salla.md
 */
it('sends /store to the approved Salla store by default', function () {
    $this->get('/store')->assertRedirect('https://ejaz.org.sa');
});

it('sends /store to the link set in the panel when it is on an allowed host', function () {
    Setting::put('store', ['url' => 'https://ejaz.org.sa/category/books']);

    $this->get('/store')->assertRedirect('https://ejaz.org.sa/category/books');
});

it('ignores a stored link on another host or without https and falls back to the approved store', function (string $url) {
    Setting::put('store', ['url' => $url]);

    $this->get('/store')->assertRedirect('https://ejaz.org.sa');
})->with(['https://evil.example/ejaz', 'http://ejaz.org.sa', 'https://ejaz.org.sa.evil.example', 'javascript:alert(1)']);

it('refuses to save a store link on another host or without https', function (string $url) {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(SiteSettings::class)
        ->fillForm(['store.url' => $url])
        ->call('save')
        ->assertHasFormErrors(['store.url']);
})->with(['https://evil.example', 'http://ejaz.org.sa']);

it('saves a store link on an allowed host, or its subdomain when a wildcard allows it', function () {
    config(['store.allowed_hosts' => ['ejaz.org.sa', '*.ejaz.org.sa']]);
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(SiteSettings::class)
        ->fillForm(['store.url' => 'https://shop.ejaz.org.sa'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::group('store')['url'])->toBe('https://shop.ejaz.org.sa');
});

it('adds the store to the main menu, opening in a new tab', function () {
    $this->seed(MenuSeeder::class);
    $item = MenuItem::query()->where('url', '/store')->first();

    expect($item)->not->toBeNull()
        ->and($item->opens_in_new_tab)->toBeTrue()
        ->and($item->is_visible)->toBeTrue();

    $this->get('/')->assertOk()->assertSee(__('home.shortcuts.store.title'));
});

it('opens the store from the home page in a new tab without a link to the page', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();
    $card = (string) str($html)->after('aria-labelledby="home-shortcuts"')->before('</section>');

    expect($card)->toMatch('#href="'.preg_quote(route('store.index'), '#').'"\s+target="_blank"\s+rel="noopener noreferrer"#');
});

it('keeps the internal store, donations and payments archived while the switch is off', function () {
    $user = userWithRole(RoleName::Member);

    $this->get('/donate')->assertNotFound();
    $this->post('/donate')->assertNotFound();
    $this->get('/store/catalog')->assertNotFound();
    $this->postJson('/webhooks/payments', [])->assertNotFound();
    $this->actingAs($user)->get('/account/payments')->assertNotFound();

    $this->actingAs($user)->get('/account')->assertOk()->assertDontSee(__('payments.account.nav'));
});

it('hides the payments resource and the donations settings while the switch is off', function () {
    $this->actingAs(userWithRole(RoleName::SuperAdmin));

    $this->get(PaymentResource::getUrl('index'))->assertForbidden();
    $this->get(SiteSettings::getUrl())->assertOk()
        ->assertDontSee(__('settings.tabs.donations'))
        ->assertDontSee(__('settings.store.enabled'));
});

it('brings the archived module back with the switch alone', function () {
    config(['payments.enabled' => true]);
    Setting::put('donations', ['enabled' => true]);

    $this->get('/donate')->assertOk();
});

it('rejects store hosts that try to slip past the domain check', function (string $url) {
    expect(StoreUrl::isAllowed($url))->toBeFalse();
})->with([
    'https://evil.com%2F.ejaz.org.sa',
    'https://evil.com%40ejaz.org.sa',
    'https://ejaz。org.sa',
    'https://ｅjaz.org.sa',
    'https://ejaz.org.sa.',
    'https://shop.ejaz.org.sa',
]);

it('accepts subdomains only through an explicit wildcard', function () {
    config(['store.allowed_hosts' => ['ejaz.org.sa', '*.ejaz.org.sa']]);

    expect(StoreUrl::isAllowed('https://shop.ejaz.org.sa'))->toBeTrue()
        ->and(StoreUrl::isAllowed('https://ejaz.org.sa'))->toBeTrue()
        ->and(StoreUrl::isAllowed('https://evilejaz.org.sa'))->toBeFalse();
});

it('keeps the archived store and donation settings when the settings are saved with the switch off', function () {
    Setting::put('store', ['url' => 'https://ejaz.org.sa', 'enabled' => true, 'intro' => 'تعريف محفوظ', 'products' => [['name' => 'منتج محفوظ', 'url' => 'https://ejaz.org.sa/p/1']]]);
    Setting::put('donations', ['enabled' => true, 'amounts' => [50, 100]]);
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(SiteSettings::class)
        ->fillForm(['store.url' => 'https://ejaz.org.sa/new'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::group('store'))->toMatchArray(['url' => 'https://ejaz.org.sa/new', 'enabled' => true, 'intro' => 'تعريف محفوظ'])
        ->and(Setting::group('store')['products'])->toHaveCount(1)
        ->and(Setting::group('donations'))->toMatchArray(['enabled' => true, 'amounts' => [50, 100]]);
});

it('adds the store to an existing menu once, before contact, shifting what follows', function () {
    $this->seed(MenuSeeder::class);
    MenuItem::query()->where('url', '/store')->delete();
    $contactSort = MenuItem::query()->whereNull('parent_id')->where('url', '/contact')->value('sort');
    $migration = require database_path('migrations/2026_10_10_130000_add_store_to_main_menu.php');

    $migration->up();
    $migration->up();

    expect(MenuItem::query()->where('url', '/store')->count())->toBe(1)
        ->and(MenuItem::query()->where('url', '/store')->value('sort'))->toBe($contactSort)
        ->and(MenuItem::query()->whereNull('parent_id')->where('url', '/contact')->value('sort'))->toBe($contactSort + 1);

    expect((string) $this->get('/')->assertOk()->getContent())->toMatch('#href="[^"]*/store"\s+target="_blank"#');
});

it('stops asking the gateway while archived, yet still closes unstarted payments and scrubs old failed donors', function () {
    $pending = Payment::factory()->create(['gateway_reference' => 'ref-1']);
    $unstarted = Payment::factory()->create();
    $old = Payment::factory()->create(['status' => PaymentStatus::Failed, 'donor_email' => 'old@example.test']);
    Payment::query()->whereKey([$pending->id, $unstarted->id])->update(['created_at' => now()->subHours(3), 'updated_at' => now()->subHours(3)]);
    Payment::query()->whereKey($old->id)->update(['created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);

    $this->artisan('ejaz:sync-pending-payments')->assertSuccessful();

    expect($pending->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($pending->fresh()->updated_at->lt(now()->subHour()))->toBeTrue()
        ->and($unstarted->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($old->fresh()->donor_email)->toBeNull();
});
