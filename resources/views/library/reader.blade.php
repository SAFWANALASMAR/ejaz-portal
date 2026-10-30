{{--
    قارئ كتاب «للقراءة فقط — بلا تحميل» (resources/js/pdf-reader.js): يرسم الصفحة في <canvas> بـ pdf.js، بلا شريط
    عارض المتصفح ولا زري التنزيل والطباعة. التقليب بالزرين وبالسحب على الجوال وبالأسهم، ورقم الصفحة والانتقال إليها،
    والتكبير والتصغير. والقائمة السياقية ممنوعة عليه، ولا يُطبع. يصعّب الحفظ ولا يمنعه منعاً تاماً (docs/decisions.md).
    $url مسار الملف بعد فحص النشر، و$title عنوان الكتاب.
--}}
@php
    $control = 'inline-flex size-10 shrink-0 items-center justify-center rounded-full border border-line bg-white text-primary transition hover:border-primary disabled:cursor-not-allowed disabled:opacity-40';
@endphp

<div data-pdf-reader="{{ $url }}" data-state="loading" data-error="{{ __('library.reader.error') }}" data-loading="{{ __('library.reader.loading') }}"
    data-page-label="{{ __('library.reader.canvas') }}" data-title="{{ $title }}"
    role="region" aria-label="{{ __('library.reader.label', ['title' => $title]) }}"
    class="mt-8 overflow-hidden rounded-2xl border border-line/70 bg-white shadow-xs select-none print:hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line/70 bg-surface px-3 py-3 sm:px-4">
        {{-- السابقة في جهة البداية (اليمين) والتالية في جهة النهاية، كتقليب الكتاب العربي --}}
        <div class="flex items-center gap-2">
            <button type="button" data-pdf-prev class="{{ $control }}" aria-label="{{ __('library.reader.previous') }}" disabled>
                {{ svg('heroicon-o-chevron-right', 'size-5', ['aria-hidden' => 'true']) }}
            </button>
            <label class="flex items-center gap-2 text-sm text-ink">
                <span class="sr-only">{{ __('library.reader.page') }}</span>
                <input type="number" inputmode="numeric" min="1" value="1" data-pdf-page disabled
                    class="w-16 rounded-lg border border-line bg-white px-2 py-1.5 text-center text-sm">
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
            <button type="button" data-pdf-zoom-reset class="min-w-16 rounded-full border border-line bg-white px-3 py-2 text-sm font-semibold text-primary tabular-nums transition hover:border-primary disabled:opacity-40"
                aria-label="{{ __('library.reader.zoom_reset') }}" disabled><span data-pdf-zoom dir="ltr">100%</span></button>
            <button type="button" data-pdf-zoom-in class="{{ $control }}" aria-label="{{ __('library.reader.zoom_in') }}" disabled>
                {{ svg('heroicon-o-plus', 'size-5', ['aria-hidden' => 'true']) }}
            </button>
        </div>
    </div>

    <div data-pdf-stage tabindex="0" aria-label="{{ __('library.reader.stage') }}" class="h-[75vh] max-h-[60rem] overflow-auto bg-surface p-2 sm:p-4">
        <canvas data-pdf-canvas role="img" class="mx-auto block bg-white shadow-sm"></canvas>
    </div>

    <p data-pdf-status role="status" class="border-t border-line/70 px-4 py-3 text-sm text-muted">{{ __('library.reader.loading') }}</p>
    <noscript><p class="px-4 pb-4 text-sm text-ink">{{ __('library.reader.no_script') }}</p></noscript>
</div>
