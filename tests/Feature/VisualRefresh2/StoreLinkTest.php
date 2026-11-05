<?php

use App\Models\Setting;
use App\Rules\StoreUrl;

/*
| رابط المتجر (المدير عبر صفوان والمشرف، 1 نوفمبر 2026): في روابط التذييل السريعة وبطاقة في «خدماتنا الإلكترونية». ومنذ صفحة
| «المتجر والتبرعات» (قرار المشرف على #179، البند 1) يذهبان إلى /store في التبويب نفسه، ومنها إلى سلة. ولا يظهران بلا رابط متجر
| صالح في إعدادات الموقع بنطاقاته المسموحة (StoreUrl)، وإلا الرابط المعتمد.
*/

it('links the store page in the footer quick links and as a services card, in the same tab', function () {
    Setting::put('store', ['url' => 'https://ejaz.org.sa/products']);

    $html = $this->get('/services')->assertOk()->getContent();
    $footer = (string) str($html)->after('data-footer-box="links"')->before('</nav>');
    $card = (string) str($html)->after('data-store-card')->before('</li>');

    expect($footer)->toContain('href="'.route('store.index').'"')
        ->toContain(__('ui.footer.links.store'))
        ->not->toContain('https://ejaz.org.sa/products')
        ->and($card)->toContain('href="'.route('store.index').'"')
        ->toContain(__('ui.services.store'))
        ->toContain('wire:navigate')
        ->not->toContain('target="_blank"');
});

it('falls back to the approved store link when the setting is empty', function () {
    expect(StoreUrl::link())->toBe('https://ejaz.org.sa');

    $this->get('/services')->assertOk()->assertSee('data-store-card', false);
});

it('hides both links when no valid store link is set', function () {
    config(['store.url' => '']);
    Setting::put('store', ['url' => 'https://evil.example.com']);

    expect(StoreUrl::link())->toBeNull();

    $html = $this->get('/services')->assertOk()->getContent();

    expect($html)->not->toContain('data-store-card')
        ->and((string) str($html)->after('data-footer-box="links"')->before('</nav>'))->not->toContain(__('ui.footer.links.store'));
});
