{{-- بطاقة مبادرة (الصورة 07 — أحدث البرامج والمبادرات): صورة، ثم الاسم والملخص، ودائرة سهم. ملونة تتعاقب درجتها (المرحلة ج) --}}
<a href="{{ route('initiatives.show', $initiative->slug) }}" wire:navigate
    class="tone-card group flex h-full flex-col overflow-hidden">
    <span class="skeleton block overflow-hidden"><img src="{{ $initiative->image ? Storage::disk('public')->url($initiative->image) : asset('images/placeholders/architecture.svg') }}"
        alt="" loading="lazy" class="aspect-[16/10] w-full object-cover transition duration-700 group-hover:scale-105"></span>
    <span class="flex flex-1 flex-col p-5">
        <span class="text-lg font-black">{{ $initiative->name }}</span>
        @if (filled($initiative->summary))
            <span class="tone-card-muted mt-2 text-sm leading-6">{{ $initiative->summary }}</span>
        @endif
        {{-- السهم «للأمام» إلى اليسار في الموقع العربي، كما في x-button --}}
        <span class="tone-pill mt-4 size-9 self-end p-0!" aria-hidden="true">
            {{ \App\Support\Icon::svg('arrow-left', 'size-4') }}
        </span>
    </span>
</a>
