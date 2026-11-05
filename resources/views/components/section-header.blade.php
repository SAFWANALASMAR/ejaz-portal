{{-- عنوان القسم يميناً، و«عرض الكل» بسهم يساراً متى كان للقسم صفحة (المرجع البصري 07). وlinkContext: ما يميّز الرابط لقارئ الشاشة
     حين يتكرر «عرض الكل» في الصفحة (اسم النوع في «الدورات والبرامج») --}}
@props(['title', 'href' => null, 'linkLabel' => null, 'linkContext' => null])

<div class="flex items-end justify-between gap-4">
    <x-section-heading :attributes="$attributes">{{ $title }}</x-section-heading>
    @if ($href)
        <a href="{{ $href }}" @navigate($href) class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
            {{ $linkLabel ?? __('site.view_all') }}
            @if (filled($linkContext))
                <span class="sr-only">{{ $linkContext }}</span>
            @endif
            {{ \App\Support\Icon::svg('arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
        </a>
    @endif
</div>
