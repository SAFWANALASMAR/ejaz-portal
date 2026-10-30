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
                <li><x-section-tile :href="route('library.section', $section->value)" :icon="$section->icon()" :label="$section->getLabel()" /></li>
            @endforeach
        </ul>
    </div>
</section>

<section class="bg-surface py-12 sm:py-16" aria-labelledby="latest">
    <div class="container-page">
        <x-section-heading id="latest">{{ __('library.list.latest') }}</x-section-heading>

        @if ($latest->isEmpty())
            <x-empty-state icon="book-open" :message="__('library.list.empty')" class="mt-6" />
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                @foreach ($latest as $item)
                    <li>@include('library.card', ['item' => $item])</li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
