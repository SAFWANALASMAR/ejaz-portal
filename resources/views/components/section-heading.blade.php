{{-- عنوان القسم (المرجع البصري): بالأخضر الداكن وبوزن عريض، يعلوه خط ذهبي قصير --}}
<div {{ $attributes }}>
    <span class="block h-1 w-12 rounded-full bg-accent" aria-hidden="true"></span>
    <h2 class="mt-3 text-2xl font-bold text-primary sm:text-3xl">{{ $slot }}</h2>
</div>
