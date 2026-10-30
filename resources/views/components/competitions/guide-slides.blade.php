{{--
    دليل الدخول شرائح: شريحة لكل خطوة برقمها وأيقونتها وجملتها من نصوص المسابقة (guide_steps). بلا سكربت تظهر الخطوات
    كلها قائمة، والسكربت (resources/js/competition-page.js) يعرض واحدة ويحرّكها بالزرين والنقاط والأسهم والسحب.
    بلغة التصور النهائي (Final-Competition): بطاقة بيضاء، ومربع أيقونة متدرج بلونه من موضع الخطوة (x-icon-tile) برقمها،
    وأزرار كبسولية. والشريحة div بدور group (لا article: دور غير مسموح له)، ووصف الدوّار والشريحة بالعربية.
--}}
@props(['steps'])

@php
    $icons = ['link', 'book-open', 'pencil-square', 'clipboard-document-check', 'play-circle', 'cursor-arrow-rays', 'paper-airplane', 'check-badge'];
    $total = count($steps);
@endphp

<section {{ $attributes->class('slides card-lift p-5 sm:p-7') }} data-slides role="region" aria-roledescription="{{ __('ui.slider.carousel') }}" aria-label="{{ __('competitions.student.page.slides_label') }}">
    <div class="space-y-4 outline-offset-4" data-slides-track tabindex="0">
        @foreach ($steps as $index => $step)
            <div class="slide group flex flex-col items-center gap-4 text-center sm:flex-row sm:items-start sm:text-start" data-slide="{{ $index + 1 }}" role="group" aria-roledescription="{{ __('ui.slider.slide') }}" aria-label="{{ __('competitions.student.page.slide_step', ['number' => $index + 1, 'total' => $total]) }}">
                <span class="relative shrink-0">
                    <x-icon-tile :icon="$icons[min($index, count($icons) - 1)]" :index="$index" size="xl" />
                    <span class="absolute -top-2 -start-2 grid size-8 place-items-center rounded-full bg-primary-dark text-sm font-bold text-white ring-2 ring-white" aria-hidden="true">{{ $index + 1 }}</span>
                </span>
                <div class="space-y-1">
                    <h3 class="font-black text-primary">{{ __('competitions.student.page.slide_step', ['number' => $index + 1, 'total' => $total]) }}</h3>
                    <p class="text-lg leading-8 text-[var(--concept-text)]">{{ $step }}</p>
                </div>
            </div>
        @endforeach
    </div>

    @if ($total > 1)
        <div class="mt-6 flex items-center justify-between gap-3" data-slides-controls hidden>
            <button type="button" data-slide-previous class="inline-flex items-center gap-1 rounded-full border border-[var(--concept-border)] p-2.5 text-sm font-bold text-primary-dark transition hover:border-[var(--concept-border-hover)] disabled:cursor-not-allowed disabled:opacity-40 sm:px-4 sm:py-2">
                {{ \App\Support\Icon::svg('chevron-right', 'size-4', ['aria-hidden' => 'true']) }}
                <span class="sr-only sm:not-sr-only">{{ __('competitions.student.page.slide_previous') }}</span>
            </button>
            <div class="flex min-w-0 flex-1 flex-wrap justify-center gap-0.5">
                @foreach ($steps as $index => $step)
                    <button type="button" data-slide-dot="{{ $index + 1 }}" aria-label="{{ __('competitions.student.page.slide_go', ['number' => $index + 1]) }}" class="group grid size-6 place-items-center">
                        <span class="block h-2 w-2 rounded-full bg-line transition-all group-aria-[current=step]:w-5 group-aria-[current=step]:bg-primary"></span>
                    </button>
                @endforeach
            </div>
            <button type="button" data-slide-next class="inline-flex items-center gap-1 rounded-full bg-primary-dark p-2.5 text-sm font-bold text-white transition hover:bg-brand disabled:cursor-not-allowed disabled:opacity-40 sm:px-4 sm:py-2">
                <span class="sr-only sm:not-sr-only">{{ __('competitions.student.page.slide_next') }}</span>
                {{ \App\Support\Icon::svg('chevron-left', 'size-4', ['aria-hidden' => 'true']) }}
            </button>
        </div>
        <p class="sr-only" aria-live="polite" data-slides-status></p>
    @endif
</section>
