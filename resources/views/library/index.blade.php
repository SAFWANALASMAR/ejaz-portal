{{--
    مكتبة إعجاز (التصور النهائي — Final-Library، المرحلة ج): البطل بصورة شريحة المكتبة في «محتوى الرئيسية»، ثم بلاطات الأقسام الأربعة
    بطاقاتٍ ملونة بالترتيب (المرئية، الصوتية، الكتب والمؤلفات، الأبحاث والدراسات — البند R9)، ثم كل قسم شريطاً متحركاً (البند R20)
    بعنوان واحد الأسلوب وخلفية واحدة (البند R24): العنوان بأيقونته يميناً و«عرض الكل» يساراً. والمقاطع مرقّمة وكل بطاقة بلون.
    والقسم الفارغ لا شريط له. والكتب والأبحاث قسمان في البيانات بقيد في القاعدة (docs/decisions/2026-11-02-library-four-sections.md). (تُخزَّن نصاً — LibraryController)
--}}
@php
    use App\Enums\LibrarySection;
@endphp

<x-page-hero :title="__('library.title')" :image="\App\Support\HomeContent::heroSlideUrl('library')" />
<x-page-sections />

<section class="pt-8 pb-6">
    <div class="mx-auto max-w-[82.5rem] px-4 sm:px-7">
        <h2 class="sr-only">{{ __('library.list.sections') }}</h2>
        <ul class="grid grid-cols-2 gap-3.5 lg:grid-cols-4" data-reveal-group data-library-tiles>
            @foreach (LibrarySection::cases() as $section)
                <li class="tone-card">
                    <a href="{{ route('library.section', $section->value) }}" wire:navigate class="group flex h-full items-center gap-3.5 px-4.5 py-4.5">
                        <x-icon-tile :icon="$section->icon()" size="md" />
                        <span class="text-lg leading-snug font-black sm:text-xl">{{ $section->getLabel() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</section>

@if ($strips->every(fn ($items) => $items->isEmpty()))
    <section class="container-page pb-12">
        <x-empty-state icon="book-open" :message="__('library.list.empty')" />
    </section>
@else
    <section class="band-sky pt-7 pb-10" aria-label="{{ __('library.list.sections') }}" data-library-strips>
        <div class="mx-auto flex max-w-[82.5rem] flex-col gap-7 px-4 sm:px-7">
            @foreach (LibrarySection::cases() as $section)
                @php($items = $strips[$section->value])
                @if ($items->isNotEmpty())
                    <div class="flex flex-col gap-3" data-library-strip="{{ $section->value }}">
                        <div class="flex items-center justify-between gap-4">
                            <x-section-heading id="library-{{ $section->value }}" :icon="$section->icon()">{{ $section->getLabel() }}</x-section-heading>
                            <a href="{{ route('library.section', $section->value) }}" wire:navigate class="more-link shrink-0">{{ __('site.view_all') }}<span class="sr-only">: {{ $section->getLabel() }}</span></a>
                        </div>

                        <x-marquee id="library-strip-{{ $section->value }}" :label="$section->getLabel()" :duration="max(30, $items->count() * 7)"
                            @class(['media-marquee', 'media-marquee-reverse' => $loop->even])>
                            @foreach ($items as $item)
                                @include('library.strip-card', ['item' => $item, 'number' => $section === LibrarySection::Videos ? $loop->iteration : null])
                            @endforeach
                        </x-marquee>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
@endif
