{{--
    بطاقة البرنامج (التصور النهائي — Final-Page): الصورة وفي زاويتها شارة الحالة، ثم النوع بخط صغير، ثم العنوان، ثم سطرا التاريخ
    وطريقة الحضور بأيقونتيهما، ثم زر داكن كبسولي بعرض كامل. ترتفع وتلمع عند المرور.
--}}
@php
    use App\Enums\ProgramStatus;
    use App\Support\Dates;

    $status = $program->status();
@endphp

<article class="group flex h-full flex-col overflow-hidden card-lift">
    <div class="relative h-40 bg-linear-to-br from-[var(--concept-sky)] to-[var(--concept-pale)]">
        <img src="{{ $program->image ? Storage::disk('public')->url($program->image) : asset('images/placeholders/architecture.svg') }}" alt="" width="640" height="360"
            class="size-full object-cover transition duration-700 group-hover:scale-105" loading="lazy">
        @include('programs.badge', ['status' => $status, 'class' => 'absolute top-3 start-3'])
    </div>

    <div class="flex flex-1 flex-col gap-2 px-5 pt-4.5 pb-5">
        <span class="text-sm font-extrabold text-primary">{{ $program->type->name }}</span>
        <h3 class="text-[1.375rem] leading-snug font-black text-primary-dark">
            <a href="{{ route('programs.show', $program->slug) }}" wire:navigate class="hover:text-primary">{{ $program->title }}</a>
        </h3>
        <p class="flex items-center gap-2 text-[15px] text-muted">{{ \App\Support\Icon::svg('calendar', 'size-[17px] shrink-0 text-primary', ['aria-hidden' => 'true']) }} {{ Dates::display($program->starts_at) }}</p>
        <p class="flex items-center gap-2 text-[15px] text-muted">{{ \App\Support\Icon::svg('map-pin', 'size-[17px] shrink-0 text-primary', ['aria-hidden' => 'true']) }} {{ $program->delivery->getLabel() }}</p>

        <a href="{{ route('programs.show', $program->slug) }}" wire:navigate
            class="mt-auto block rounded-full bg-primary-dark py-3 text-center text-lg font-extrabold text-white transition hover:bg-brand">
            {{ $status === ProgramStatus::Open ? __('programs.list.register') : __('programs.list.details') }}
        </a>
    </div>
</article>
