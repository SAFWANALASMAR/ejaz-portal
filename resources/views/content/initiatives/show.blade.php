{{-- صفحة المبادرة: رأس بصورتها، ثم مقاطع الوصف، ثم الأهداف، ثم معرض الصور --}}
@php
    $goals = array_values(array_filter((array) $initiative->goals, fn ($goal) => is_string($goal) && trim($goal) !== ''));
    $gallery = array_values(array_filter((array) $initiative->gallery, fn ($path) => is_string($path) && $path !== ''));
@endphp

<x-page-hero :title="$initiative->name" :summary="$initiative->summary"
    :image="$initiative->image ? Storage::disk('public')->url($initiative->image) : null" />

<x-content-blocks :blocks="$initiative->blocks" />

@if ($goals !== [])
    <section class="bg-sand py-12 sm:py-16" aria-labelledby="goals">
        <div class="container-page">
            <x-section-heading id="goals">{{ __('content.initiatives.goals') }}</x-section-heading>
            <ol class="mt-8 grid gap-4 sm:grid-cols-2">
                @foreach ($goals as $goal)
                    <li class="flex items-start gap-4 rounded-2xl bg-white p-5 shadow-xs">
                        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-gold font-bold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                        <span class="pt-1 leading-7 text-ink">{{ $goal }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif

@if ($gallery !== [])
    <section class="py-12 sm:py-16" aria-labelledby="gallery">
        <div class="container-page">
            <x-section-heading id="gallery">{{ __('content.initiatives.gallery') }}</x-section-heading>
            <ul class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ($gallery as $path)
                    <li>
                        <img src="{{ Storage::disk('public')->url($path) }}" loading="lazy" class="aspect-square w-full rounded-2xl object-cover"
                            alt="{{ __('content.initiatives.gallery_alt', ['number' => $loop->iteration, 'name' => $initiative->name]) }}">
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
