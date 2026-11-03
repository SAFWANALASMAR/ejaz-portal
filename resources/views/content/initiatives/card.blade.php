{{-- بطاقة مبادرة (الصورة 07 — أحدث البرامج والمبادرات): صورة، ثم الاسم والملخص، ودائرة سهم. ملونة تتعاقب درجتها (المرحلة ج) --}}
<a href="{{ route('initiatives.show', $initiative->slug) }}" wire:navigate
    class="tone-card group flex h-full flex-col overflow-hidden">
    <span class="skeleton block overflow-hidden"><img src="{{ $initiative->image ? Storage::disk('public')->url($initiative->image) : \App\Support\NeutralImage::forSeed($initiative->id) }}"
        alt="" loading="lazy" class="aspect-[16/10] w-full object-cover transition duration-700 group-hover:scale-105"></span>
    <span class="flex flex-1 flex-col p-5">
        <span class="line-clamp-2 text-lg leading-snug font-black">{{ $initiative->name }}</span>
        @if (filled($initiative->summary))
            <span class="tone-card-muted mt-2 line-clamp-3 text-sm leading-6">{{ $initiative->summary }}</span>
        @endif
        {{-- السهم «للأمام» إلى اليسار في الموقع العربي، كما في x-button --}}
        <span class="mt-auto self-end pt-4" aria-hidden="true"><span class="tone-pill size-9 p-0!">
            {{ \App\Support\Icon::svg('arrow-left', 'size-4') }}
        </span></span>
    </span>
</a>
