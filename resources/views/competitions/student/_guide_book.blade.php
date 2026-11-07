{{-- دليل الجمعية كاملاً تحت خطوات الدخول: زر إلى صفحة الكتاب في المكتبة بقارئها «للقراءة فقط — بلا تحميل» (pdf-reader، #101).
    ويُخفى ما لم يُختر كتاب أو لم يعد منشوراً للقراءة فقط (Competition::guideBook — docs/decisions/2026-11-07-brahin-guide-id-time.md) --}}
@if ($book = $competition->guideBook())
    <x-button :href="route('library.show', [$book->section->value, $book->slug])" arrow class="mt-4" data-guide-book>{{ __('competitions.student.page.guide_book_link') }}</x-button>
@endif
