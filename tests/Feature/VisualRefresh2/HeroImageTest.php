<?php

use App\Jobs\GenerateHeroVariants;
use App\Support\HeroImage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
| صورة شريحة البطل بنسختين WebP مصغّرتين وsrcset (مراجعة المشرف على #111، صورة LCP): الأصل وطلب التوليد في الطابور حتى
| تُولَّد، ثم النسختان. ولا توليد لغير صورة نقطية في القرص العام.
*/

it('serves the original and queues the variants until they exist, then a srcset of both widths', function () {
    Storage::fake('public');
    Queue::fake();
    Storage::disk('public')->put('albums/event.jpg', imageBytes('jpeg', 2400, 1200));

    expect(HeroImage::sources('albums/event.jpg'))->toBe(['src' => Storage::disk('public')->url('albums/event.jpg'), 'srcset' => null]);
    Queue::assertPushed(GenerateHeroVariants::class, fn (GenerateHeroVariants $job): bool => $job->path === 'albums/event.jpg');

    (new GenerateHeroVariants('albums/event.jpg'))->handle();

    foreach (HeroImage::WIDTHS as $width) {
        $variant = Storage::disk('public')->get(HeroImage::variantPath('albums/event.jpg', $width));
        expect(getimagesizefromstring((string) $variant))->toMatchArray([0 => $width, 'mime' => 'image/webp']);
    }

    $sources = HeroImage::sources('albums/event.jpg');
    expect($sources['src'])->toEndWith('-1600.webp')
        ->and($sources['srcset'])->toContain('-800.webp 800w')->toContain('-1600.webp 1600w');
});

it('does not enlarge a small image', function () {
    Storage::fake('public');
    Storage::disk('public')->put('settings/hero.png', imageBytes('png', 600, 300));

    (new GenerateHeroVariants('settings/hero.png'))->handle();

    expect(getimagesizefromstring((string) Storage::disk('public')->get(HeroImage::variantPath('settings/hero.png', 1600)))[0])->toBe(600);
});

it('queues nothing for a vector, an outside or a climbing path', function (string $path) {
    Storage::fake('public');
    Queue::fake();

    expect(HeroImage::sources($path)['srcset'])->toBeNull();
    Queue::assertNothingPushed();
})->with(['images/placeholders/architecture.svg', '../secret.jpg', 'albums/../../x.png']);
