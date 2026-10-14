<?php

use App\Models\MenuItem;

// قائمة الرأس صف واحد (docs/reviews/ready-fix-header-single-row.md). والعرض نفسه مختبر في المتصفح:
// tests/browser/header-row.spec.mjs
it('keeps every top menu item on one line and shows the bar from the measured width', function () {
    MenuItem::factory()->create(['label' => 'خدماتنا الإلكترونية', 'url' => '/services']);
    $parent = MenuItem::factory()->create(['label' => 'المركز الإعلامي', 'url' => '/media']);
    MenuItem::factory()->create(['parent_id' => $parent->id, 'label' => 'الأخبار', 'url' => '/media/news']);

    $dom = new DOMDocument;
    @$dom->loadHTML($this->get('/')->assertOk()->getContent());
    $x = new DOMXPath($dom);

    $items = $x->query('//nav[@data-main-nav]/ul/li/*[self::a or self::button]');
    expect($items->length)->toBe(2);

    foreach ($items as $item) {
        expect($item->getAttribute('class'))->toContain('whitespace-nowrap')->toContain('shrink-0');
    }

    // الشريط من نقطة nav المقيسة، وزر القائمة وقائمة الجوال دونها
    expect($x->query("//nav[@data-main-nav][contains(@class, 'nav:flex')]")->length)->toBe(1)
        ->and($x->query("//button[@data-toggle='mobile-menu'][contains(@class, 'nav:hidden')]")->length)->toBe(1)
        ->and($x->query("//div[@id='mobile-menu'][contains(@class, 'nav:hidden')]")->length)->toBe(1)
        ->and($x->query("//header[@data-site-header]//*[contains(@class, 'xl:flex') or contains(@class, 'xl:hidden')]")->length)->toBe(0);
});
