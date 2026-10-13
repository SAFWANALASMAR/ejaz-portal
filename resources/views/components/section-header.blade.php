{{-- عنوان القسم يميناً، و«عرض الكل» بسهم يساراً متى كان للقسم صفحة (المرجع البصري 07) --}}
@props(['title', 'href' => null, 'linkLabel' => null])

<div class="flex items-end justify-between gap-4">
    <x-section-heading :attributes="$attributes">{{ $title }}</x-section-heading>
    @if ($href)
        <a href="{{ $href }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
            {{ $linkLabel ?? __('site.view_all') }}
            {{ svg('heroicon-o-arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
        </a>
    @endif
</div>
