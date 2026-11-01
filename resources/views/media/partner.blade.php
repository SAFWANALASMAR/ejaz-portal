{{--
    بطاقة شريك: الشعار والاسم. والرابط يُفحص مرة أخرى عند العرض، فلا يظهر رابط ضار ولو وصل إلى
    القاعدة، ويفتح خارجياً بلا صلة بالصفحة (noopener noreferrer).
--}}
@php
    $url = \App\Rules\SafeUrl::isSafe($partner->url, allowInternal: false) ? $partner->url : null;
    $tag = $url !== null ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($url !== null) href="{{ $url }}" target="_blank" rel="noopener noreferrer" @endif
    class="flex h-full flex-col items-center justify-center gap-3 p-5 text-center card-lift">
    @if ($partner->logo)
        <img src="{{ Storage::disk('public')->url($partner->logo) }}" alt="" loading="lazy" class="h-20 w-full object-contain">
    @endif
    <span class="text-sm font-semibold text-ink">{{ $partner->name }}</span>
    @if ($url !== null)
        <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
    @endif
</{{ $tag }}>
