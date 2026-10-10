<?php

use App\Enums\RoleName;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\MenuItem;
use App\Models\Setting;
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

it('saves a store link on an allowed host or its subdomain', function () {
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
