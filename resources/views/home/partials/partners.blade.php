{{-- مقطع الشركاء: شعاراتهم بترتيبهم في اللوحة --}}
<section class="bg-surface py-12 lg:py-16" aria-labelledby="home-partners">
    <div class="container-page">
        <div class="flex items-end justify-between gap-4">
            <x-section-heading id="home-partners">{{ __('partners.title') }}</x-section-heading>
            <a href="{{ route('media.partners') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
                {{ __('site.view_all') }}
                {{ svg('heroicon-o-arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
            </a>
        </div>

        <ul class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($partners as $partner)
                <li>@include('media.partner', ['partner' => $partner])</li>
            @endforeach
        </ul>
    </div>
</section>
