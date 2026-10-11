{{-- مقطع صورة: بعرض المحتوى بزوايا مستديرة، ووصف اختياري تحتها. والنص البديل حقله، وإلا الوصف (الفارغ زخرفية) --}}
@if (is_string($data['picture'] ?? null) && $data['picture'] !== '')
    <figure class="container-page py-8">
        <img src="{{ Storage::disk('public')->url($data['picture']) }}" alt="{{ $data['alt'] ?? $data['caption'] ?? '' }}" loading="lazy"
            class="max-h-[32rem] w-full rounded-2xl object-cover">
        @if (filled($data['caption'] ?? null))
            <figcaption class="mt-3 text-center text-sm text-muted">{{ $data['caption'] }}</figcaption>
        @endif
    </figure>
@endif
