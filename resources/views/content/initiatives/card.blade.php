{{-- بطاقة مبادرة (الصورة 07 — أحدث البرامج والمبادرات): صورة، ثم الاسم والملخص، ودائرة سهم --}}
<a href="{{ route('initiatives.show', $initiative->slug) }}"
    class="group flex h-full flex-col overflow-hidden rounded-2xl border border-line/70 bg-white shadow-xs transition hover:-translate-y-0.5 hover:shadow-md">
    <img src="{{ $initiative->image ? Storage::disk('public')->url($initiative->image) : asset('images/placeholders/architecture.svg') }}"
        alt="" loading="lazy" class="aspect-[16/10] w-full object-cover">
    <span class="flex flex-1 flex-col p-5">
        <span class="text-lg font-bold text-ink">{{ $initiative->name }}</span>
        @if (filled($initiative->summary))
            <span class="mt-2 text-sm leading-6 text-muted">{{ $initiative->summary }}</span>
        @endif
        {{-- السهم «للأمام» إلى اليسار في الموقع العربي، كما في x-button --}}
        <span class="mt-4 inline-flex size-9 items-center justify-center self-end rounded-full bg-primary text-white transition group-hover:bg-primary-dark" aria-hidden="true">
            {{ svg('heroicon-o-arrow-left', 'size-4') }}
        </span>
    </span>
</a>
