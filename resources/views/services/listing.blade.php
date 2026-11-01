{{--
    فرص نوع واحد (الانضمام أو التوظيف أو التطوع) بالنمط الموحد للأقسام (الصورة 05): رأس القسم،
    ثم بطاقات الفرص المفتوحة، لكل بطاقة العنوان والوصف المختصر وموعد الإغلاق ودائرة سهم.
--}}
@php
    use App\Enums\OpeningKind;
    use App\Support\Dates;
@endphp

<x-layouts.app :title="$kind->getLabel()">
    <x-page-hero :title="$kind->getLabel()" />
    <x-page-intro :slug="$kind->introSlug()" />

    <section class="py-10 sm:py-14">
        <div class="container-page">
            @if ($openings->isEmpty())
                <x-empty-state icon="briefcase" :message="$kind === OpeningKind::Membership ? __('services.listing.membership_closed') : __('services.listing.empty')" />
            @else
                <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                    @foreach ($openings as $opening)
                        <li>
                            <a href="{{ route('services.openings.show', $opening->slug) }}" wire:navigate
                                class="tone-card group flex h-full flex-col p-6">
                                <x-icon-tile :icon="$kind->icon()" size="md" />
                                <span class="mt-3 block text-lg font-black">{{ $opening->title }}</span>
                                @if (filled($opening->summary))
                                    <span class="tone-card-muted mt-2 block text-sm leading-6">{{ $opening->summary }}</span>
                                @endif
                                <span class="tone-card-muted mt-auto flex items-center justify-between gap-3 pt-5 text-sm">
                                    <span class="flex items-center gap-1.5">
                                        {{ \App\Support\Icon::svg('calendar', 'size-4') }}
                                        @if ($opening->closes_at)
                                            {{ __('services.listing.closes_at') }}: {{ Dates::display($opening->closes_at) }}
                                        @else
                                            {{ __('services.listing.open_ended') }}
                                        @endif
                                    </span>
                                    <span class="tone-pill size-9 shrink-0 p-0!" aria-hidden="true">
                                        {{ \App\Support\Icon::svg('arrow-left', 'size-4') }}
                                    </span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                {{ $openings->links('partials.pagination') }}
            @endif
        </div>
    </section>
</x-layouts.app>
