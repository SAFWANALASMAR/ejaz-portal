{{-- التنقل بين صفحات القوائم: السابق والتالي ورقم الصفحة، بالعربية ومن اليمين --}}
@if ($paginator->hasPages())
    <nav class="mt-10 flex items-center justify-between gap-4" aria-label="{{ __('site.pagination') }}">
        @if ($paginator->onFirstPage())
            <span class="rounded-full border border-line px-5 py-2 text-sm text-muted/60">{{ __('pagination.previous') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" wire:navigate class="rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-primary hover:border-primary">{{ __('pagination.previous') }}</a>
        @endif

        <span class="text-sm text-muted">{{ __('site.page_of', ['page' => $paginator->currentPage(), 'pages' => $paginator->lastPage()]) }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" wire:navigate class="rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-primary hover:border-primary">{{ __('pagination.next') }}</a>
        @else
            <span class="rounded-full border border-line px-5 py-2 text-sm text-muted/60">{{ __('pagination.next') }}</span>
        @endif
    </nav>
@endif
