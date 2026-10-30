{{--
    بطاقة البرنامج (الصورة 06): الصورة وفي زاويتها شارة الحالة، ثم العنوان والوصف، ثم سطر
    التاريخ بأيقونة تقويم والنوع بأيقونة، ثم زر رئيسي بعرض كامل.
--}}
@php
    use App\Enums\ProgramStatus;
    use App\Support\Dates;

    $status = $program->status();
@endphp

<article class="shine group flex h-full flex-col overflow-hidden card-lift">
    <div class="relative aspect-[16/9] bg-primary-soft">
        @if ($program->image)
            <img src="{{ Storage::disk('public')->url($program->image) }}" alt="" class="size-full object-cover" loading="lazy">
        @else
            <img src="{{ asset('images/placeholders/architecture.svg') }}" alt="" class="size-full object-cover" loading="lazy">
        @endif
        @include('programs.badge', ['status' => $status, 'class' => 'absolute top-3 start-3'])
    </div>

    <div class="flex flex-1 flex-col p-5 text-center">
        <h3 class="text-lg font-bold text-ink">
            <a href="{{ route('programs.show', $program->slug) }}" wire:navigate class="hover:text-primary">{{ $program->title }}</a>
        </h3>
        @if (filled($program->summary))
            <p class="mt-2 text-sm leading-6 text-muted">{{ $program->summary }}</p>
        @endif

        <p class="mt-auto flex items-center justify-between gap-3 pt-4 text-sm text-muted">
            <span class="flex items-center gap-1.5">{{ \App\Support\Icon::svg('calendar', 'size-4') }} {{ Dates::display($program->starts_at) }}</span>
            <span class="flex items-center gap-1.5">{{ \App\Support\Icon::svg('academic-cap', 'size-4 text-accent') }} {{ $program->type->name }}</span>
        </p>

        <x-button class="mt-4 w-full" :href="route('programs.show', $program->slug)">
            {{ $status === ProgramStatus::Open ? __('programs.list.register') : __('programs.list.details') }}
        </x-button>
    </div>
</article>
