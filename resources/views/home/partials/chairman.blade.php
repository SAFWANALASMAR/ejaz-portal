{{--
    كلمة رئيس مجلس الإدارة (الخطة الاستراتيجية ص4 — ملاحظات المدير التنفيذي، 25 أكتوبر 2026): بشكل شريط كلمة خادم الحرمين
    الشريفين، بحركة دخول عند الظهور (data-reveal، وبلا حركة لمن طلب تقليلها). الافتتاح ثم أول فقرة فعلية من المقدمة
    (قرار صفوان، 26 أكتوبر 2026)، و«اقرأ الكلمة كاملة»
    يعرضها كلها في نافذة (<dialog>)، وبلا سكربت تبقى في <details> تحتها.
--}}
@php($paragraphs = \App\Support\HomeContent::paragraphs($chairman['full']))

<section class="relative overflow-hidden bg-primary-dark text-white" aria-labelledby="home-chairman">
    <span class="brand-emblem -end-10 top-6 hidden h-72 w-72 opacity-10 md:block" aria-hidden="true"></span>

    <div class="container-page relative grid items-center gap-10 py-14 lg:grid-cols-[1.25fr_1fr] lg:gap-12 lg:py-20">
        <div data-reveal>
            <h2 id="home-chairman" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-semibold text-accent-light ring-1 ring-white/20">
                {{ \App\Support\Icon::svg('chat-bubble-left-right', 'size-4', ['aria-hidden' => 'true']) }}
                {{ $chairman['heading'] }}
            </h2>

            <div class="mt-6 space-y-3 text-lg leading-[2] font-medium text-white/95 sm:text-xl sm:leading-[2]">
                @foreach (\App\Support\HomeContent::paragraphs($chairman['excerpt']) as $excerptParagraph)
                    <p>{{ $excerptParagraph }}</p>
                @endforeach
            </div>

            <div class="mt-6 border-s-4 border-accent-light ps-4">
                <p class="text-lg font-bold">{{ $chairman['name'] }}</p>
                <p class="mt-1 text-sm leading-6 text-white/80">{{ $chairman['title'] }}</p>
            </div>

            <details class="group mt-8" data-dialog-source="chairman-word">
                <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-full bg-white px-6 py-2.5 font-semibold text-primary-dark shadow-sm transition hover:bg-accent-light [&::-webkit-details-marker]:hidden"
                    data-dialog-open="chairman-word">
                    {{ __('home.sections.chairman.read_more') }}
                    {{ \App\Support\Icon::svg('arrow-left', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                </summary>
                <div class="mt-6 space-y-4 rounded-2xl bg-white/5 p-6 leading-8 ring-1 ring-white/15">
                    @foreach ($paragraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </details>
        </div>

        <div class="relative mx-auto w-full max-w-xs sm:max-w-sm" data-reveal style="--reveal-delay: 150ms">
            <span class="absolute -inset-3 -rotate-3 rounded-full bg-linear-to-br from-accent-light via-brand to-secondary opacity-80" aria-hidden="true"></span>
            <img src="{{ $chairman['image'] }}" alt="{{ $chairman['image_alt'] }}" loading="lazy"
                class="relative aspect-square w-full rounded-full bg-white object-cover object-top shadow-2xl ring-8 ring-primary-dark">
        </div>
    </div>

    {{-- الكلمة كاملة في نافذة متى توفّر السكربت (resources/js/home.js) --}}
    <dialog id="chairman-word" aria-labelledby="chairman-word-title"
        class="m-auto max-h-[85vh] w-[min(46rem,calc(100%-2rem))] overflow-y-auto rounded-3xl bg-white p-0 text-ink shadow-2xl backdrop:bg-primary-dark/70">
        <div class="sticky top-0 flex items-center justify-between gap-4 border-b border-line bg-white px-6 py-4">
            <h3 id="chairman-word-title" class="text-lg font-bold text-primary">{{ $chairman['heading'] }}</h3>
            <button type="button" data-dialog-close class="rounded-full p-2 text-muted hover:bg-surface hover:text-ink" aria-label="{{ __('home.sections.chairman.close') }}">
                {{ \App\Support\Icon::svg('x-mark', 'size-6', ['aria-hidden' => 'true']) }}
            </button>
        </div>
        <div class="space-y-4 px-6 py-6 text-lg leading-9">
            @foreach ($paragraphs as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
            <div class="border-s-4 border-accent ps-4 pt-2">
                <p class="font-bold">{{ $chairman['name'] }}</p>
                <p class="text-sm text-muted">{{ $chairman['title'] }}</p>
            </div>
        </div>
    </dialog>
</section>
