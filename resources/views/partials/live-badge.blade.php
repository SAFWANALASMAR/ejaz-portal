{{-- شارة البث في الرأس: تظهر آلياً لبث جارٍ أو قادم وتختفي بعده ($liveStream من App\View\Composers\SiteChromeComposer). في آخر صف
     القائمة على الحاسوب، بنصها أو نقطة نابضة إن لم يتسع الصف (data-compact — app.js وconcept.css)، وأول قائمة الجوال واللوحي، فلا
     تزيح المخطوطة عن منتصف الصف الأول (docs/decisions/2026-11-02-header-final.md) --}}
@if ($liveStream ?? null)
    @php($liveLabel = __($liveStream['live'] ? 'livestream.header.live' : 'livestream.header.upcoming'))
    <a href="{{ route('live') }}" wire:navigate data-live-badge title="{{ $liveLabel }}" @class(['inline-flex h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-full bg-white px-4 text-sm font-bold text-red-700 hover:bg-red-50', $class ?? null])>
        <span data-live-dot @class(['size-2 shrink-0 rounded-full bg-red-600', 'animate-pulse' => $liveStream['live']]) aria-hidden="true"></span>
        <span data-live-label>{{ $liveLabel }}</span>
    </a>
@endif
