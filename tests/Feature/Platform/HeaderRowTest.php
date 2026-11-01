<?php

use App\Models\MenuItem;

// الرأس صفّان (أمر التصور النهائي، النسخة 63 — البندان R2 وR3): الأول الشعار والمخطوطة ومربع الترخيص والدخول والبحث، والثاني
// القائمة كبسولات متساوية في صف واحد، وتحت nav زر القائمة وقائمة الجوال. والعرض نفسه مختبر في المتصفح: tests/browser/header-row.spec.mjs
it('puts the menu on its own second row of equal capsules and shows the bar from the measured width', function () {
    MenuItem::factory()->create(['label' => 'الخدمات الإلكترونية', 'url' => '/services']);
    $parent = MenuItem::factory()->create(['label' => 'المركز الإعلامي', 'url' => '/media']);
    MenuItem::factory()->create(['parent_id' => $parent->id, 'label' => 'الأخبار', 'url' => '/media/news']);

    $dom = new DOMDocument;
    @$dom->loadHTML($this->get('/')->assertOk()->getContent());
    $x = new DOMXPath($dom);

    $items = $x->query("//nav[@data-main-nav]/ul[contains(@class, 'nav-capsules')]/li/*[self::a or self::button]");
    expect($items->length)->toBe(2);

    foreach ($items as $item) {
        expect($item->getAttribute('class'))->toBe('nav-capsule')
            ->and($x->query('.//span[@data-icon-tile]', $item)->length)->toBe(1)
            ->and($x->query(".//span[contains(@class, 'truncate')]", $item)->length)->toBe(1);
    }

    // الصف الأول قبل صف القائمة داخل الرأس الثابت نفسه، والشريط من نقطة nav المقيسة، وزر القائمة وقائمة الجوال دونها
    expect($x->query("//header[@data-site-header][contains(@class, 'sticky')][contains(@class, 'top-0')]/div[contains(@class, 'header-top')]/following-sibling::nav[@data-main-nav]")->length)->toBe(1)
        ->and($x->query("//nav[@data-main-nav][contains(@class, 'nav:block')]")->length)->toBe(1)
        ->and($x->query("//button[@data-toggle='mobile-menu'][contains(@class, 'nav:hidden')]")->length)->toBe(1)
        ->and($x->query("//div[@id='mobile-menu'][contains(@class, 'nav:hidden')]")->length)->toBe(1);
});

it('shows the two licence lines from the panel in the first row, and no supervision line in the header or the footer', function () {
    $html = $this->get('/')->assertOk()->getContent();
    $header = (string) str($html)->after('data-site-header')->before('</header>');

    expect($header)->toContain('data-header-licence')
        ->toContain(e(__('home.sections.header.licensed_by')))
        ->toContain(e(__('home.sections.header.license_number')))
        ->and($html)->not->toContain(e(__('home.sections.footer.supervision')));
});
