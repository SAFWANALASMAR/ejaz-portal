{{--
    زر «تعديل هذه الصفحة» لمن له pages.edit_all وحده (المسؤول التقني والمدير التنفيذي — قرار صفوان، 31 أكتوبر 2026): يفتح شاشة
    الصفحة في اللوحة (EditablePages::adminUrl). يُرسم في التخطيط مع كل طلب خارج أي محتوى مخزَّن، فلا يدخل نص صفحة يقرؤه زائر.
    والانتقال إلى اللوحة تحميل كامل (لا wire:navigate). وفي جهة البداية، فلا يغطي زر المساعد في جهة النهاية.
--}}
@auth
    @can('pages.edit_all')
        @php($editUrl = \App\Support\EditablePages::adminUrl(request()->route()))
        @if ($editUrl !== null)
            <a href="{{ $editUrl }}" data-page-edit-button
                class="fixed bottom-4 start-4 z-40 inline-flex items-center gap-2 rounded-full bg-primary-dark px-4 py-3 font-semibold text-white shadow-lg transition hover:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                {{ \App\Support\Icon::svg('pencil-square', 'size-5 shrink-0', ['aria-hidden' => 'true']) }}
                <span>{{ __('page_editor.actions.edit_button') }}</span>
            </a>
        @endif
    @endcan
@endauth
