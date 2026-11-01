<?php

use App\Models\Setting;
use App\Rules\StoreUrl;

/*
| رابط متجر الجمعية على سلة (المدير عبر صفوان والمشرف، 1 نوفمبر 2026): في روابط التذييل السريعة وبطاقة في «خدماتنا الإلكترونية»،
| بتبويب جديد (noopener). والرابط من إعدادات الموقع بنطاقاته المسموحة (StoreUrl)، وإلا الرابط المعتمد، ولا يظهر بلا رابط صالح.
*/

it('links the store in the footer quick links and as a services card, in a new tab', function () {
    Setting::put('store', ['url' => 'https://ejaz.org.sa/products']);

    $html = $this->get('/services')->assertOk()->getContent();
    $footer = (string) str($html)->after('data-footer-box="links"')->before('</nav>');
    $card = (string) str($html)->after('data-store-card')->before('</li>');

    expect($footer)->toContain('href="https://ejaz.org.sa/products" target="_blank" rel="noopener noreferrer"')
        ->toContain(__('ui.footer.links.store'))
        ->and($card)->toContain('href="https://ejaz.org.sa/products" target="_blank" rel="noopener noreferrer"')
        ->toContain(__('ui.services.store'))
        ->not->toContain('wire:navigate');
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
