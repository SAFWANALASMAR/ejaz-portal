<?php

use App\Jobs\GenerateHeroVariants;
use App\Support\ContentCache;
use App\Support\HeroImage;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository;
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

// مراجعة المشرف على #111 (1 نوفمبر 2026)، المنخفضة 3: توليد النسخ يمسح مفتاح الرئيسية وحده، لا ذاكرة المحتوى كلها
it('clears only the home page from the content cache after generating the variants', function () {
    Storage::fake('public');
    Storage::disk('public')->put('albums/event.jpg', imageBytes('jpeg', 1200, 600));
    ContentCache::remember('governance.index', fn (): string => 'المخزّن');
    ContentCache::remember('home', fn (): string => 'الرئيسية القديمة');

    (new GenerateHeroVariants('albums/event.jpg'))->handle();

    expect(ContentCache::remember('governance.index', fn (): string => 'جديد'))->toBe('المخزّن')
        ->and(ContentCache::remember('home', fn (): string => 'الرئيسية الجديدة'))->toBe('الرئيسية الجديدة');
});

// والمنخفضة 4: الصورة المرفوضة (تالفة أو أكبر من الحد) تُعلَّم فلا تُرسل مهمتها عند كل بناء للرئيسية
it('marks a rejected image and stops queueing it on every build', function () {
    Storage::fake('public');
    Queue::fake();
    Storage::disk('public')->put('albums/broken.jpg', 'ليست صورة');

    HeroImage::sources('albums/broken.jpg');
    Queue::assertPushed(GenerateHeroVariants::class, 1);

    (new GenerateHeroVariants('albums/broken.jpg'))->handle();
    // انتهت المهمة فزال قفل تفرّدها (ShouldBeUnique)، كما في الطابور الحقيقي
    (new UniqueLock(app(Repository::class)))->release(new GenerateHeroVariants('albums/broken.jpg'));

    expect(HeroImage::sources('albums/broken.jpg'))->toBe(['src' => Storage::disk('public')->url('albums/broken.jpg'), 'srcset' => null]);
    HeroImage::sources('albums/broken.jpg');
    Queue::assertPushed(GenerateHeroVariants::class, 1);
});

it('retries a replaced image under a new path', function () {
    Storage::fake('public');
    Queue::fake();
    Storage::disk('public')->put('albums/broken.jpg', 'ليست صورة');
    (new GenerateHeroVariants('albums/broken.jpg'))->handle();
    (new UniqueLock(app(Repository::class)))->release(new GenerateHeroVariants('albums/broken.jpg'));

    Storage::disk('public')->put('albums/fixed.jpg', imageBytes('jpeg', 1200, 600));
    HeroImage::sources('albums/fixed.jpg');

    Queue::assertPushed(GenerateHeroVariants::class, fn (GenerateHeroVariants $job): bool => $job->path === 'albums/fixed.jpg');
});
