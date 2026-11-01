{{--
    الحالة الفارغة (نطاق صفوان 18:15: «حالات فارغة جميلة»): مربع أيقونة متدرج يطفو بخفة، والرسالة، وزر اختياري في الـ slot.
    الرسالة من ملف ترجمة الوحدة. والحركة تتوقف لمن طلب تقليلها وخارج الشاشة.
--}}
@props(['icon' => 'inbox-stack', 'message', 'tone' => 'light'])

<div data-empty-state {{ $attributes->class(['empty-state']) }}>
    <x-icon-tile :icon="$icon" :shade="$tone" size="xl" class="float-soft" data-loop />
    <p class="max-w-md leading-8 text-muted">{{ $message }}</p>
    {{ $slot }}
</div>
