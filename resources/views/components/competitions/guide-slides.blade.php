{{--
    دليل الدخول شرائح: شريحة لكل خطوة برقمها وأيقونتها وجملتها من نصوص المسابقة (guide_steps). بلا سكربت تظهر الخطوات
    كلها قائمة، والسكربت (resources/js/competition-page.js) يعرض واحدة ويحرّكها بالزرين والنقاط والأسهم والسحب.
    والأيقونات بترتيب خطوات الدليل (Heroicons، ويحوّلها Icon::svg إلى Phosphor بعد #95)، وما زاد يأخذ الأخيرة.
--}}
@props(['steps'])

@php
    $icons = ['heroicon-o-link', 'heroicon-o-book-open', 'heroicon-o-pencil-square', 'heroicon-o-clipboard-document-check',
        'heroicon-o-play-circle', 'heroicon-o-cursor-arrow-rays', 'heroicon-o-paper-airplane', 'heroicon-o-check-badge'];
    $total = count($steps);
@endphp

<section {{ $attributes->class('slides rounded-2xl border border-line/70 bg-white p-4 shadow-xs sm:p-6') }} data-slides aria-roledescription="carousel" aria-label="{{ __('competitions.student.page.slides_label') }}">
    <div class="space-y-4 outline-offset-4" data-slides-track tabindex="0">
        @foreach ($steps as $index => $step)
            <article class="slide flex flex-col items-center gap-4 text-center sm:flex-row sm:items-start sm:text-start" data-slide="{{ $index + 1 }}" role="group" aria-roledescription="slide" aria-label="{{ __('competitions.student.page.slide_step', ['number' => $index + 1, 'total' => $total]) }}">
                <span class="relative grid size-20 shrink-0 place-items-center rounded-2xl bg-primary-soft text-brand" aria-hidden="true">
                    {{ svg($icons[min($index, count($icons) - 1)], 'size-10') }}
                    <span class="absolute -top-2 -start-2 grid size-8 place-items-center rounded-full bg-primary text-sm font-bold text-white">{{ $index + 1 }}</span>
                </span>
                <div class="space-y-1">
                    <h3 class="font-bold text-accent">{{ __('competitions.student.page.slide_step', ['number' => $index + 1, 'total' => $total]) }}</h3>
                    <p class="text-lg leading-8 text-ink">{{ $step }}</p>
                </div>
            </article>
        @endforeach
    </div>

    @if ($total > 1)
        <div class="mt-6 flex items-center justify-between gap-3" data-slides-controls hidden>
            <button type="button" data-slide-previous class="inline-flex items-center gap-1 rounded-full border border-primary/30 p-2.5 text-sm font-semibold text-primary sm:px-4 sm:py-2 transition hover:bg-primary-soft disabled:cursor-not-allowed disabled:opacity-40">
                {{ svg('heroicon-o-chevron-right', 'size-4', ['aria-hidden' => 'true']) }}
                <span class="sr-only sm:not-sr-only">{{ __('competitions.student.page.slide_previous') }}</span>
            </button>
            <div class="flex min-w-0 flex-1 flex-wrap justify-center gap-0.5">
                @foreach ($steps as $index => $step)
                    <button type="button" data-slide-dot="{{ $index + 1 }}" aria-label="{{ __('competitions.student.page.slide_go', ['number' => $index + 1]) }}" class="group grid size-6 place-items-center">
                        <span class="block h-2 w-2 rounded-full bg-line transition-all group-aria-[current=step]:w-5 group-aria-[current=step]:bg-primary"></span>
                    </button>
                @endforeach
            </div>
            <button type="button" data-slide-next class="inline-flex items-center gap-1 rounded-full bg-primary p-2.5 text-sm sm:px-4 sm:py-2 font-semibold text-white transition hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-40">
                <span class="sr-only sm:not-sr-only">{{ __('competitions.student.page.slide_next') }}</span>
                {{ svg('heroicon-o-chevron-left', 'size-4', ['aria-hidden' => 'true']) }}
            </button>
        </div>
        <p class="sr-only" aria-live="polite" data-slides-status></p>
    @endif
</section>
