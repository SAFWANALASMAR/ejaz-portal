<?php

use App\Models\Announcement;

// فجوات تقرير القبول v0.15.0 (القسم ٧). والقياس في المتصفح (طول التذييل، وعرض «شارك واربح»، وأهداف اللمس) في
// tests/browser/acceptance-polish.spec.mjs

it('gives every announcement image its intrinsic width and height on the home and announcements pages', function () {
    Announcement::factory()->create(['title' => 'إعلان بصورة']);

    foreach (['/', '/media/announcements'] as $path) {
        $html = (string) $this->get($path)->assertOk()->getContent();

        preg_match_all('#<img[^>]*src="[^"]*/announcements/[^"]*"[^>]*>#', $html, $images);

        expect($images[0], $path)->not->toBeEmpty();
        foreach ($images[0] as $image) {
            expect($image)->toContain('width="400"')->toContain('height="300"');
        }
    }
});

it('keeps every image on the home page sized, so nothing shifts while it loads', function () {
    Announcement::factory()->create();

    $html = (string) $this->get('/')->assertOk()->getContent();
    $main = str($html)->after('<main')->before('</main>')->toString();

    preg_match_all('#<img\b[^>]*>#', $main, $images);
    $unsized = array_values(array_filter($images[0], fn (string $image): bool => ! preg_match('#\bwidth="\d+"#', $image) || ! preg_match('#\bheight="\d+"#', $image)));

    expect($unsized)->toBe([]);
});
