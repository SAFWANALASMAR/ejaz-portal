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
        <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach (LibrarySection::cases() as $section)
                <li>
                    <a href="{{ route('library.section', $section->value) }}"
                        class="flex h-full flex-col items-center gap-3 rounded-2xl border border-line/70 bg-white p-6 text-center shadow-xs transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md">
                        <span class="text-accent" aria-hidden="true">{{ svg('heroicon-o-'.$section->icon(), 'size-9') }}</span>
                        <span class="font-semibold text-ink">{{ $section->getLabel() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</section>

<section class="bg-surface py-12 sm:py-16" aria-labelledby="latest">
    <div class="container-page">
        <x-section-heading id="latest">{{ __('library.list.latest') }}</x-section-heading>

        @if ($latest->isEmpty())
            <p class="mt-6 text-muted">{{ __('library.list.empty') }}</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($latest as $item)
                    <li>@include('library.card', ['item' => $item])</li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
