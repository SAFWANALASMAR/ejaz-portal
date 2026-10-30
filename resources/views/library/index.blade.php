{{--
    المكتبة (النمط الموحد — الصورة 05): رأس القسم، ثم بلاطات الأقسام الأربعة، ثم أحدث الإضافات
    ببطاقات الصورتين 06 و07. (تُخزَّن نصاً — LibraryController)
--}}
@php
    use App\Enums\LibrarySection;
@endphp

<x-page-hero :title="__('library.title')" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        <h2 class="sr-only">{{ __('library.list.sections') }}</h2>
        <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-reveal-group>
            @foreach (LibrarySection::cases() as $section)
                <li><x-section-tile :index="$loop->index" :href="route('library.section', $section->value)" :icon="$section->icon()" :label="$section->getLabel()" /></li>
            @endforeach
        </ul>
    </div>
</section>

@php
    $videos = $latest->filter(fn ($item) => $item->section === LibrarySection::Videos)->take(3);
    $books = $latest->filter(fn ($item) => in_array($item->section, [LibrarySection::Books, LibrarySection::Research], true))->take(4);
    $others = $latest->reject(fn ($item) => $videos->contains($item) || $books->contains($item));
@endphp

@if ($videos->isNotEmpty())
    <section class="pb-12" aria-labelledby="library-videos">
        <div class="mx-auto max-w-[82.5rem] px-4 sm:px-7">
            <div class="mb-4.5 flex items-center justify-between gap-4">
                <h2 id="library-videos" class="flex items-center gap-2.5 text-[1.875rem] font-black text-primary-dark">
                    {{ LibrarySection::Videos->getLabel() }}
                    <span class="icon-line">{{ \App\Support\Icon::svg(LibrarySection::Videos->icon(), 'size-7', ['aria-hidden' => 'true']) }}</span>
                </h2>
                <a href="{{ route('library.section', LibrarySection::Videos->value) }}" wire:navigate class="more-link">{{ __('site.view_all') }}</a>
            </div>
            <ul class="scroll-row" data-reveal-group>
                @foreach ($videos as $item)
                    <li>@include('library.card', ['item' => $item])</li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

@if ($books->isNotEmpty())
    <section class="band-sky" aria-labelledby="library-books">
        <div class="mx-auto max-w-[82.5rem] px-4 pt-11 pb-13 sm:px-7">
            <x-section-heading id="library-books" center class="mb-6.5">{{ LibrarySection::Books->getLabel() }}</x-section-heading>
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-reveal-group>
                @foreach ($books as $item)
                    <li>@include('library.card', ['item' => $item])</li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

@if ($latest->isEmpty() || $others->isNotEmpty())
    <section class="py-12 sm:py-16" aria-labelledby="latest">
        <div class="mx-auto max-w-[82.5rem] px-4 sm:px-7">
            <x-section-heading id="latest">{{ __('library.list.latest') }}</x-section-heading>

            @if ($latest->isEmpty())
                <x-empty-state icon="book-open" :message="__('library.list.empty')" class="mt-6" />
            @else
                <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                    @foreach ($others as $item)
                        <li>@include('library.card', ['item' => $item])</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
