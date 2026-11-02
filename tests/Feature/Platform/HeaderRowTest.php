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
        ->toContain('برقم 1000723700')
        ->and($html)->not->toContain(e(__('home.sections.footer.supervision')));
});

// ترتيب صفوان (2 نوفمبر 2026 — docs/decisions/2026-11-02-header-final.md، ويلغي البند ٢٥): الصف الأول ثلاثة أعمدة بعرض الشاشة،
// الرمز ثم المخطوطة في الوسط ثم طرف فيه مربع الترخيص بجانبها ثم البحث ثم الدخول. والمواضع نفسها مقيسة في
// tests/browser/header-final.spec.mjs
it('orders the first row as mark, centred calligraphy, licence, search then login, across the full width', function () {
    $dom = new DOMDocument;
    @$dom->loadHTML($this->get('/')->assertOk()->getContent());
    $x = new DOMXPath($dom);

    $row = $x->query("//header[@data-site-header]/div[contains(concat(' ', @class, ' '), ' header-top ')]")->item(0);
    expect($row)->not->toBeNull()
        ->and($row->getAttribute('class'))->not->toContain('max-w-');

    $columns = $x->query('./*', $row);
    expect($columns->length)->toBe(3)
        ->and($x->query('.//img[@data-brand-mark]', $columns->item(0))->length)->toBe(1)
        ->and($x->query('.//img[@data-logo-calligraphy]', $columns->item(1))->length)->toBe(1)
        ->and($columns->item(2)->getAttribute('class'))->toContain('header-end');

    $end = $x->query('./*', $columns->item(2));
    expect($end->item(0)->hasAttribute('data-header-licence'))->toBeTrue()
        ->and($end->item(1)->getAttribute('class'))->toContain('header-actions');

    // البحث قبل الدخول في الشيفرة، فالدخول في أقصى اليسار
    $actions = $x->query(".//*[@data-search-open or contains(@class, 'login-pill')]", $end->item(1));
    expect($actions->item(0)->hasAttribute('data-search-open'))->toBeTrue()
        ->and($actions->item(1)->getAttribute('class'))->toContain('login-pill');
});

it('keeps the header the same size on scroll: no shrink factor or scroll attribute left in the styles and scripts', function () {
    foreach (['resources/css/concept.css', 'resources/css/visual.css', 'resources/css/app.css', 'resources/js/app.js'] as $file) {
        expect(file_get_contents(base_path($file)))->not->toContain('var(--hs')->not->toContain('data-scrolled');
    }
});
