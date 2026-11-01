{{--
    بطاقة مطوية (details/summary): تُفتح بالنقر وبلوحة المفاتيح بلا سكربت، ومحتواها في الصفحة لمحركات البحث، والمتصفح
    يعلن حالتها (مفتوحة أو مطوية) بنفسه فلا aria-expanded يكذب إن تعطّل السكربت. والعنوان نص لا <h2>، لأن summary زر
    يُسقط دلالة العنوان. والانسدال الناعم والسهم الدوّار في competition-page.css بلا حركة لمن يطلب تقليلها.
--}}
@props(['title', 'icon', 'index' => 0])

{{-- بلغة التصور النهائي (Final-Competition): بطاقة بيضاء بحد، ومربع أيقونة متدرج بلونه من موضعه (index) --}}
<details {{ $attributes->class('collapsible card-lift group') }} data-collapsible>
    <summary class="flex cursor-pointer list-none items-center gap-4 rounded-2xl p-4 sm:p-5 [&::-webkit-details-marker]:hidden">
        <x-icon-tile :icon="str_replace('heroicon-o-', '', $icon)" :index="$index" size="sm" />
        <span class="flex-1 text-lg font-black text-primary-dark sm:text-xl">{{ $title }}</span>
        <span class="collapsible-chevron grid size-8 shrink-0 place-items-center rounded-full bg-surface text-primary" aria-hidden="true">
            {{ svg('heroicon-o-chevron-down', 'size-5') }}
        </span>
    </summary>
    <div class="px-4 pb-5 sm:px-5 sm:ps-20">
        {{ $slot }}
    </div>
</details>
