{{--
    البحث السريع (Ctrl+K أو ⌘K، وزر البحث في الرأس — محور المدير «التقانة»): نافذة <dialog> تبحث فوراً في صفحات القائمة
    الرئيسية بأسمائها، وتقترح البحث بالكلمة في الأقسام التي لها بحث قائم (الحوكمة والأخبار وأقسام المكتبة) بمساراتها الموجودة.
    بلا طلب إلى الخادم ولا مكتبة (resources/js/search-palette.js). وبلا سكربت يبقى زر البحث رابطاً إلى الحوكمة.
    $siteMenu من SiteChromeComposer.
--}}
@php
    $pages = [];

    foreach (($siteMenu ?? collect()) as $item) {
        if ($item->linkable ?? true) {
            $pages[] = ['label' => $item->label, 'url' => $item->href()];
        }

        foreach ($item->children as $child) {
            $pages[] = ['label' => $child->label, 'url' => $child->href(), 'parent' => $item->label];
        }
    }

    $sections = [];

    if (Route::has('governance.index')) {
        $sections[] = ['label' => __('ui.search.sections.governance'), 'url' => route('governance.index')];
    }

    if (Route::has('media.news.index')) {
        $sections[] = ['label' => __('ui.search.sections.news'), 'url' => route('media.news.index')];
    }

    if (Route::has('library.section')) {
        foreach (\App\Enums\LibrarySection::cases() as $section) {
            $sections[] = ['label' => $section->getLabel(), 'url' => route('library.section', $section->value)];
        }
    }
    // ترميز JSON يهرّب < و> و& فلا يُغلق وسم السكربت من نص في القائمة
    $data = json_encode(['pages' => $pages, 'sections' => $sections, 'in' => __('ui.search.in', ['q' => ':q', 'section' => ':section'])], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
@endphp

<dialog data-search-dialog aria-labelledby="search-palette-title"
    class="search-palette m-auto mt-[12vh] w-[min(40rem,calc(100%-2rem))] overflow-hidden rounded-3xl bg-white p-0 text-ink shadow-2xl backdrop:bg-primary-dark/60 backdrop:backdrop-blur-sm">
    <form method="dialog" class="contents" data-search-form>
        <div class="flex items-center gap-3 border-b border-line px-5 py-3">
            {{ \App\Support\Icon::svg('magnifying-glass', 'size-5 shrink-0 text-primary', ['aria-hidden' => 'true']) }}
            <h2 id="search-palette-title" class="sr-only">{{ __('ui.search.title') }}</h2>
            <input type="search" data-search-input maxlength="100" autocomplete="off" role="combobox" aria-expanded="true"
                aria-controls="search-palette-results" aria-autocomplete="list" aria-label="{{ __('ui.search.title') }}"
                placeholder="{{ __('ui.search.placeholder') }}"
                class="min-w-0 flex-1 border-0 bg-transparent py-2 text-lg outline-none placeholder:text-muted focus:ring-0">
            <button type="submit" value="close" data-search-close class="rounded-full p-2 text-muted hover:bg-surface hover:text-ink" aria-label="{{ __('ui.search.close') }}">
                {{ \App\Support\Icon::svg('x-mark', 'size-5', ['aria-hidden' => 'true']) }}
            </button>
        </div>
    </form>

    <div class="max-h-[55vh] overflow-y-auto p-3">
        <ul id="search-palette-results" role="listbox" aria-label="{{ __('ui.search.pages') }}" data-search-results class="space-y-1"></ul>
        <p data-search-empty hidden class="px-3 py-4 text-sm text-muted">{{ __('ui.search.empty') }}</p>
    </div>

    <p class="border-t border-line bg-surface px-5 py-2 text-xs text-muted">{{ __('ui.search.hint') }}</p>

    <script type="application/json" data-search-data>{!! $data !!}</script>
</dialog>
