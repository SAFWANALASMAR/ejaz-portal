{{-- شارة البث في الرأس: تظهر آلياً لبث جارٍ أو قادم وتختفي بعده ($liveStream من App\View\Composers\SiteChromeComposer). معلّقة تحت صف
     القائمة على الحاسوب (concept.css)، وأول قائمة الجوال واللوحي، فلا تزيح المخطوطة عن منتصف الصف الأول (docs/decisions/2026-11-02-header-final.md) --}}
@if ($liveStream ?? null)
    <a href="{{ route('live') }}" wire:navigate data-live-badge @class(['inline-flex h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-full bg-white px-4 text-sm font-bold text-red-700 hover:bg-red-50', $class ?? null])>
        <span @class(['size-2 rounded-full bg-red-600', 'animate-pulse' => $liveStream['live']]) aria-hidden="true"></span>
        {{ __($liveStream['live'] ? 'livestream.header.live' : 'livestream.header.upcoming') }}
    </a>
@endif
