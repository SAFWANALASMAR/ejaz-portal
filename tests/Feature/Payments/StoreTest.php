<?php

use App\Contracts\StoreCatalog;
use App\Enums\RoleName;
use App\Filament\Pages\SiteSettings;
use App\Models\Setting;
use App\Services\Store\FakeStoreCatalog;
use App\Support\Store\StoreProduct;
use Livewire\Livewire;

// المتجر الداخلي المؤرشف ببطاقات إعدادات الموقع، وصفحة «المتجر والتبرعات» بمشغّل manual الافتراضي (config/store.php)
beforeEach(function () {
    config(['store.driver' => 'settings']);
});

it('hides the archived internal store page until it is enabled from the panel', function () {
    $this->get('/store/catalog')->assertNotFound();
});

it('shows the store link and the product cards entered from the panel', function () {
    Setting::put('store', [
        'enabled' => true,
        'url' => 'https://ejaz.org.sa',
        'intro' => 'وصف تجريبي',
        'products' => [['name' => 'منتج تجريبي', 'description' => 'وصف المنتج', 'url' => 'https://example.salla.sa/p/1']],
    ]);

    $this->get('/store/catalog')->assertOk()
        ->assertSee(__('store.title'))
        ->assertSee('وصف تجريبي')
        ->assertSee('منتج تجريبي')
        ->assertSee('https://example.salla.sa/p/1')
        ->assertSee('https://ejaz.org.sa')
        ->assertSee(__('store.shop'));
});

it('renders whatever catalog is bound, so the Salla API can replace the manual cards', function () {
    Setting::put('store', ['enabled' => true, 'url' => 'https://example.salla.sa']);
    $this->app->instance(StoreCatalog::class, new FakeStoreCatalog([new StoreProduct('من الواجهة البرمجية', null, 'https://example.salla.sa/p/9')]));

    $this->get('/store/catalog')->assertOk()->assertSee('من الواجهة البرمجية');
});

it('saves the store and donation settings and rejects unsafe links and bad amounts', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'store.enabled' => true,
            'store.url' => 'https://ejaz.org.sa',
            'store.products' => [['name' => 'منتج', 'url' => 'https://example.salla.sa/p/1']],
            'donations.enabled' => true,
            'donations.amounts' => [['amount' => 50], ['amount' => 100]],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::group('store')['url'])->toBe('https://ejaz.org.sa')
        ->and(Setting::group('donations')['amounts'])->toBe([50, 100]);

    Livewire::test(SiteSettings::class)
        ->fillForm(['store.url' => 'javascript:alert(1)', 'store.products' => [['name' => 'منتج', 'url' => '/internal']], 'donations.amounts' => [['amount' => 0]]])
        ->call('save')
        ->assertHasFormErrors(['store.url', 'store.products.0.url', 'donations.amounts.0.amount']);
});

it('never renders an unsafe product link even if one reaches the database', function () {
    Setting::put('store', ['enabled' => true, 'url' => 'javascript:alert(1)', 'products' => [['name' => 'منتج', 'url' => 'javascript:alert(2)']]]);

    $this->get('/store/catalog')->assertOk()->assertDontSee('javascript:', false);
});
