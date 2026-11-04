const fs = require('fs');
let f = 'resources/views/components/page-hero.blade.php', s = fs.readFileSync(f, 'utf8');
const rep = (a, b) => { if (!s.includes(a)) { console.error('MISSING', f, a.slice(0, 70)); process.exit(1); } s = s.replace(a, b); };
rep(`@props(['title', 'summary' => null, 'image' => null, 'eyebrow' => null, 'icon' => null, 'compact' => false])`, `@props(['title', 'summary' => null, 'image' => null, 'images' => [], 'eyebrow' => null, 'icon' => null, 'compact' => false])`);
rep(`    $heroSrc = \App\Support\HomeContent::pageHeroUrl(request()->route()?->getName()) ?? $image ?? \App\Support\NeutralImage::forPage(request()->route()?->getName());`, `    $heroSrc = \App\Support\HomeContent::pageHeroUrl(request()->route()?->getName()) ?? $image ?? ($images[0] ?? null) ?? \App\Support\NeutralImage::forPage(request()->route()?->getName());
    // صور تتبدل في الإطار (رأس «مكتبة إعجاز» — البند ٢٥): أكثر من صورة وحدها، وإلا الصورة الواحدة ثابتة
    $rotating = count($images) > 1 ? array_values($images) : [];`);
rep(`            <span class="page-hero-frame">
                <img src="{{ $heroSrc }}" alt="" width="1280" height="640" data-page-hero-image>
            </span>`, `            @if ($rotating !== [])
                {{-- الصور تتبدل بتلاشٍ ناعم بالدوّار القائم (carousel.js، data-carousel)، ولا تبدأ لمن طلب تقليل الحركة، ويوقفها المرور --}}
                <span class="page-hero-frame" data-carousel data-autoplay data-interval="5000" data-page-hero-rotator>
                    @foreach ($rotating as $index => $src)
                        <img src="{{ $src }}" alt="" width="1280" height="640" data-carousel-item data-page-hero-image @if ($index === 0) data-active @else loading="lazy" @endif>
                    @endforeach
                </span>
            @else
                <span class="page-hero-frame">
                    <img src="{{ $heroSrc }}" alt="" width="1280" height="640" data-page-hero-image>
                </span>
            @endif`);
fs.writeFileSync(f, s);
f = 'resources/views/library/index.blade.php'; s = fs.readFileSync(f, 'utf8');
rep(`<x-page-hero :title="__('library.title')" :image="\App\Support\HomeContent::heroSlideUrl('library') ?? once(fn () => \App\Models\LibraryItem::query()->published()->whereNotNull('thumbnail')->newestFirst()->first()?->thumbnailUrl())" />`, `{{-- صور الرأس تتبدل (البند ٢٥): صور المكتبة من اللوحة ثم أغلفة أحدث العناصر حتى أربع (LibraryHeroImages)، وتُحسب مرة لعرضي الذاكرة --}}
<x-page-hero :title="__('library.title')" :images="once(fn () => \App\Support\LibraryHeroImages::urls())" />`);
fs.writeFileSync(f, s);
