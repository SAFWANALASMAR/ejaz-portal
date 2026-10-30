<?php

use Illuminate\Support\Facades\Blade;

/*
| مكونات الدفعة الثانية المشتركة: x-icon-tile بحالتيه، والدوّار، وشارة الرقم، وعنوان القسم بشارته وزخرفته، والفاصل.
*/

it('renders one icon tile with the gradient variant on light and glass on dark', function () {
    $gradient = Blade::render('<x-icon-tile icon="trophy" tone="violet" size="lg" />');
    $glass = Blade::render('<x-icon-tile icon="trophy" variant="glass" />');
    $unknown = Blade::render('<x-icon-tile icon="trophy" variant="neon" />');

    expect($gradient)->toContain('data-icon-tile')->toContain('data-variant="gradient"')->toContain('data-tone="violet"')->toContain('icon-tile-lg')
        ->toContain('viewBox="0 0 256 256"')
        ->and($glass)->toContain('data-variant="glass"')
        ->and($unknown)->toContain('data-variant="gradient"');
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
    $heading = Blade::render('<x-section-heading eyebrow="خدماتنا" description="وصف">عنوان</x-section-heading>');

    expect($heading)->toContain('data-section-eyebrow')->toContain('data-heading-ornament')->toContain('وصف')
        ->and(Blade::render('<x-divider-ornament />'))->toContain('data-divider-ornament');
});
