{{--
    قارئ كتاب «للقراءة فقط — بلا تحميل» (resources/js/pdf-reader.js): يرسم الصفحة في <canvas> بـ pdf.js، بلا شريط
    عارض المتصفح ولا زري التنزيل والطباعة. التقليب بالزرين وبالسحب على الجوال وبالأسهم، ورقم الصفحة والانتقال إليها،
    والتكبير والتصغير. والقائمة السياقية ممنوعة عليه، ولا يُطبع. يصعّب الحفظ ولا يمنعه منعاً تاماً (docs/decisions.md).
    $url مسار الملف بعد فحص النشر، و$title عنوان الكتاب.
--}}
@php
    // بلغة التصور النهائي (Final-Library): شريط أزرق داكن بأزرار دائرية بإطار أبيض
    $control = 'inline-flex size-10 shrink-0 items-center justify-center rounded-full border border-white/30 bg-white/10 text-white transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40';
@endphp

<div data-pdf-reader="{{ $url }}" data-state="loading" data-error="{{ __('library.reader.error') }}" data-loading="{{ __('library.reader.loading') }}"
    data-page-label="{{ __('library.reader.canvas') }}" data-title="{{ $title }}"
    role="region" aria-label="{{ __('library.reader.label', ['title' => $title]) }}"
    class="concept-panel mt-8 overflow-hidden select-none print:hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 bg-primary-dark px-3 py-3 text-white sm:px-4">
        {{-- السابقة في جهة البداية (اليمين) والتالية في جهة النهاية، كتقليب الكتاب العربي --}}
        <div class="flex items-center gap-2">
            <button type="button" data-pdf-prev class="{{ $control }}" aria-label="{{ __('library.reader.previous') }}" disabled>
                {{ svg('heroicon-o-chevron-right', 'size-5', ['aria-hidden' => 'true']) }}
            </button>
            <label class="flex items-center gap-2 text-sm text-white">
                <span class="sr-only">{{ __('library.reader.page') }}</span>
                <input type="number" inputmode="numeric" min="1" value="1" data-pdf-page disabled
                    class="w-16 rounded-lg border border-white/30 bg-white px-2 py-1.5 text-center text-sm text-ink">
                <span aria-hidden="true">{{ __('library.reader.of') }}</span>
                <span data-pdf-total>…</span>
            </label>
            <button type="button" data-pdf-next class="{{ $control }}" aria-label="{{ __('library.reader.next') }}" disabled>
                {{ svg('heroicon-o-chevron-left', 'size-5', ['aria-hidden' => 'true']) }}
            </button>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" data-pdf-zoom-out class="{{ $control }}" aria-label="{{ __('library.reader.zoom_out') }}" disabled>
                {{ svg('heroicon-o-minus', 'size-5', ['aria-hidden' => 'true']) }}
            </button>
            <button type="button" data-pdf-zoom-reset class="min-w-16 rounded-full border border-white/30 bg-white/10 px-3 py-2 text-sm font-semibold text-white tabular-nums transition hover:bg-white/20 disabled:opacity-40"
                aria-label="{{ __('library.reader.zoom_reset') }}" disabled><span data-pdf-zoom dir="ltr">100%</span></button>
            <button type="button" data-pdf-zoom-in class="{{ $control }}" aria-label="{{ __('library.reader.zoom_in') }}" disabled>
                {{ svg('heroicon-o-plus', 'size-5', ['aria-hidden' => 'true']) }}
            </button>
        </div>
    </div>

    {{-- الصفحة تملأ الإطار (البند R14): بلا حواش، والإطار بطول الصفحة حتى 85% من الشاشة، وما زاد يُمرَّر داخله (.pdf-stage) --}}
    <div data-pdf-stage tabindex="0" aria-label="{{ __('library.reader.stage') }}" class="pdf-stage">
        <canvas data-pdf-canvas role="img" class="mx-auto block bg-white"></canvas>
    </div>

    <p data-pdf-status role="status" class="border-t border-line/70 px-4 py-3 text-sm text-muted">{{ __('library.reader.loading') }}</p>
    <noscript><p class="px-4 pb-4 text-sm text-ink">{{ __('library.reader.no_script') }}</p></noscript>
</div>
