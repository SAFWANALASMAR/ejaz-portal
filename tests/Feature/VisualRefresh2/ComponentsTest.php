<?php

use Illuminate\Support\Facades\Blade;

/*
| مكونات الدفعة الثانية المشتركة: x-icon-tile بحالتيه، والدوّار، وشارة الرقم، وعنوان القسم بشارته وزخرفته، والفاصل.
*/

// أمر التصور النهائي، النسخة 63 (البند R5): المتدرجة افتراضاً بدرجتها من موضع البطاقة في CSS (بلا data-shade)، وshade يثبّت
// درجة من الأربع، والاسم القديم tone مثله، والزجاجية على الداكن، والخطية عند الطلب. ولا لون خارج الأربع
it('renders one icon tile: gradient shaded by position by default, an explicit shade, glass on dark and line on request', function () {
    $first = Blade::render('<x-icon-tile icon="trophy" size="lg" />');
    $indexed = Blade::render('<x-icon-tile icon="trophy" :index="3" />');
    $explicit = Blade::render('<x-icon-tile icon="trophy" shade="dark" />');
    $legacy = Blade::render('<x-icon-tile icon="trophy" tone="light" />');
    $glass = Blade::render('<x-icon-tile icon="trophy" variant="glass" />');
    $line = Blade::render('<x-icon-tile icon="trophy" variant="line" />');
    $unknown = Blade::render('<x-icon-tile icon="trophy" variant="neon" tone="violet" />');

    expect($first)->toContain('data-icon-tile')->toContain('data-variant="gradient"')->toContain('icon-tile gt')->toContain('icon-tile-lg')
        ->toContain('viewBox="0 0 256 256"')->not->toContain('data-shade')->not->toContain('data-tone')
        ->and($indexed)->not->toContain('data-shade')->not->toContain('index')
        ->and($explicit)->toContain('data-shade="dark"')
        ->and($legacy)->toContain('data-shade="light"')
        ->and($glass)->toContain('data-variant="glass"')
        ->and($line)->toContain('data-variant="line"')
        ->and($unknown)->toContain('data-variant="gradient"')->not->toContain('violet')->not->toContain('data-shade');
});

it('renders a carousel with its arrows, dots, status and pause button only when it has several slides', function () {
    $many = Blade::render('<x-carousel label="تجربة" :count="3" autoplay><div data-carousel-item data-active>أ</div><div data-carousel-item>ب</div><div data-carousel-item>ج</div></x-carousel>');
    $one = Blade::render('<x-carousel label="تجربة" :count="1" autoplay><div data-carousel-item data-active>أ</div></x-carousel>');

    expect($many)->toContain('data-autoplay')->toContain('data-carousel-status')->toContain('01 / 03')
        ->toContain('aria-label="'.__('ui.slider.pause').'"')->toContain('aria-pressed="false"')
        ->and(substr_count($many, 'data-carousel-dot class'))->toBe(3)
        ->and($one)->not->toContain('data-carousel-prev')->not->toContain('data-autoplay');
});

it('renders the counter number in full for readers without JavaScript', function () {
    $badge = Blade::render('<x-stat-badge :value="12500" label="مستفيد" suffix="+" />');

    expect($badge)->toContain('data-stat-badge')->toContain('data-count-to="12500"')->toContain('12,500+');
});

it('renders the section heading with its eyebrow, ornament and description', function () {
    $heading = Blade::render('<x-section-heading eyebrow="خدماتنا" description="وصف" center>عنوان</x-section-heading>');

    expect($heading)->toContain('data-section-eyebrow')->toContain('data-heading-ornament')->toContain('وصف');
});

it('draws the client error pages on the concept hero with the home and contact buttons', function () {
    $this->get('/vr2-no-such-page')->assertNotFound()
        ->assertSeeInOrder(['data-error-page', 'concept-hero-scrim', 'error-code', '404', __('errors.404.title'), 'title-bar', 'class="hero-cta', __('errors.home'), __('errors.contact')], false);
});

it('keeps a dark background on the account sidebar, whose links are white, lighter than the header (director note 29)', function () {
    $css = file_get_contents(resource_path('css/concept.css'));

    expect($css)->toMatch('/\.account-nav \{[^}]*background: var\(--blue-850\)/');
});
