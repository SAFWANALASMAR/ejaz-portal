{{--
    بطاقة البرنامج (التصور النهائي — Final-Page، المرحلة ج): بطاقة ملونة تتعاقب درجتها (tone-card)، الصورة وفي زاويتها شارة الحالة،
    ثم النوع بخط صغير، ثم العنوان، ثم سطرا التاريخ وطريقة الحضور بأيقونتيهما، ثم زر كبسولي بعرض كامل. ترتفع عند المرور.
--}}
@php
    use App\Enums\ProgramStatus;
    use App\Support\Dates;

    $status = $program->status();
@endphp

<article class="tone-card group flex h-full flex-col overflow-hidden">
    <div class="tone-card-media h-40">
        <img src="{{ $program->image ? Storage::disk('public')->url($program->image) : \App\Support\NeutralImage::forSeed($program->id) }}" alt="" width="640" height="360"
            class="size-full object-cover transition duration-700 group-hover:scale-105" loading="lazy">
        @include('programs.badge', ['status' => $status, 'class' => 'absolute top-3 start-3'])
    </div>

    <div class="flex flex-1 flex-col gap-2 px-5 pt-4.5 pb-5">
        <span class="tone-chip">{{ $program->type->name }}</span>
        <h3 class="line-clamp-2 text-[1.375rem] leading-snug font-black">
            <a href="{{ route('programs.show', $program->slug) }}" wire:navigate class="hover:underline">{{ $program->title }}</a>
        </h3>
        <p class="tone-card-muted flex items-center gap-2 text-[15px]">{{ \App\Support\Icon::svg('calendar', 'size-[17px] shrink-0', ['aria-hidden' => 'true']) }} {{ Dates::display($program->starts_at) }}</p>
        <p class="tone-card-muted flex items-center gap-2 text-[15px]">{{ \App\Support\Icon::svg('map-pin', 'size-[17px] shrink-0', ['aria-hidden' => 'true']) }} {{ $program->delivery->getLabel() }}</p>

        <a href="{{ route('programs.show', $program->slug) }}" wire:navigate class="tone-pill mt-auto w-full py-3 text-lg">
            {{ $status === ProgramStatus::Open ? __('programs.list.register') : __('programs.list.details') }}
        </a>
    </div>
</article>
