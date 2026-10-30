{{-- الشركاء: شعاراتهم بترتيبهم في اللوحة، ولكل شريك رابط موقعه (تُخزَّن نصاً — MediaController) --}}
<x-page-hero :title="__('partners.title')" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        @if ($partners->isEmpty())
            <p class="text-muted">{{ __('partners.list.empty') }}</p>
        @else
            <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4" data-reveal-group>
                @foreach ($partners as $partner)
                    <li>@include('media.partner', ['partner' => $partner])</li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
