{{--
    عنوان قسم في صفحات نماذج الحساب (الدفعة ٤ من طلب لوحة الإدارة، 4 نوفمبر 2026): مربع أيقونة متدرج كالبطاقات، والعنوان من ملف
    الترجمة في الـ slot، وخط فاصل خفيف تحته يجمع حقول القسم. الأنماط في resources/css/account-polish.css (.acct-section-title).
    والمعرّف اختياري لروابط الأقسام (#password و#reading-tracking).
--}}
@props(['icon', 'id' => null])

<h2 @if ($id) id="{{ $id }}" @endif {{ $attributes->class(['acct-section-title scroll-mt-24']) }} data-account-section-title>
    <x-icon-tile :icon="$icon" shade="dark" size="sm" class="shrink-0" />
    <span>{{ $slot }}</span>
</h2>
