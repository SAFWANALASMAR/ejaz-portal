{{-- مقطع الشركاء والرعاة (ملاحظة المدير التنفيذي): شعاراتهم بترتيبهم في اللوحة، ومجموعتان متى وُجد الراعي --}}
@php
    use App\Enums\PartnerKind;

    $groups = $partners->groupBy(fn ($partner) => $partner->kind->value);
    $grouped = $groups->has(PartnerKind::Sponsor->value);
@endphp

<section class="bg-white py-12 lg:py-16" aria-labelledby="home-partners">
    <div class="container-page">
        <div class="flex items-end justify-between gap-4">
            <x-section-heading id="home-partners">{{ __('home.partners.title') }}</x-section-heading>
            <a href="{{ route('media.partners') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
                {{ __('site.view_all') }}
                {{ svg('heroicon-o-arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
            </a>
        </div>

        @foreach (PartnerKind::cases() as $kind)
            @if ($groups->has($kind->value))
                @if ($grouped)
                    <h3 class="mt-8 text-lg font-bold text-primary">{{ __($kind === PartnerKind::Sponsor ? 'home.partners.sponsors' : 'home.partners.partners') }}</h3>
                @endif
                <ul @class(['grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6', 'mt-4' => $grouped, 'mt-8' => ! $grouped])>
                    @foreach ($groups[$kind->value] as $partner)
                        <li>@include('media.partner', ['partner' => $partner])</li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    </div>
</section>
