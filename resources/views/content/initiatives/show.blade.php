{{-- صفحة المبادرة: رأس بصورتها، ثم مقاطع الوصف، ثم الأهداف، ثم معرض الصور --}}
@php
    $goals = array_values(array_filter((array) $initiative->goals, fn ($goal) => is_string($goal) && trim($goal) !== ''));
    $gallery = array_values(array_filter((array) $initiative->gallery, fn ($path) => is_string($path) && $path !== ''));
@endphp

<x-page-hero :title="$initiative->name" :summary="$initiative->summary"
    :image="$initiative->image ? Storage::disk('public')->url($initiative->image) : null" />

<x-content-blocks :blocks="$initiative->blocks" />

@if ($goals !== [])
    <section class="band-sky py-12 sm:py-16" aria-labelledby="goals">
        <div class="container-page">
            <x-section-heading id="goals">{{ __('content.initiatives.goals') }}</x-section-heading>
            <ol class="mt-8 grid gap-4 sm:grid-cols-2">
                @foreach ($goals as $goal)
                    <li class="flex items-start gap-4 rounded-2xl bg-white p-5 shadow-xs">
                        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-accent font-bold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
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
            <div class="mt-8">
                <x-gallery :images="collect($gallery)->values()->map(fn (string $path, int $i): array => [
                    'src' => Storage::disk('public')->url($path),
                    'alt' => __('content.initiatives.gallery_alt', ['number' => $i + 1, 'name' => $initiative->name]),
                ])->all()" />
            </div>
        </div>
    </section>
@endif
