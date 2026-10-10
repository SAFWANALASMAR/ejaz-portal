{{--
    قسم الجمعية العمومية (الصورة 05 — النمط الموحد): رأس القسم بصورة وعنوان فوق تدرج داكن، ثم بلاطات
    بيضاء مربعة بأيقونة واسم. والشعار التسويقي في الصورة لجهة أخرى (التصحيحات ٣)، فلا نص تحت العنوان.
    للزائر البلاطات الأربع، ولمن يصل إلى المنطقة بلاطاتها الثلاث والاجتماع القادم، ولصاحب حساب غير عضو
    تنبيه ورابط طلب الانضمام.
--}}
@php
    $tiles = $user === null ? [['route' => 'login', 'label' => 'login', 'icon' => 'user']] : [];

    if ($user === null || $member) {
        $tiles = [
            ...$tiles,
            ['route' => 'assembly.meetings.index', 'label' => 'meetings', 'icon' => 'calendar-days'],
            ['route' => 'assembly.minutes.index', 'label' => 'minutes', 'icon' => 'document-text'],
            ['route' => 'assembly.files.index', 'label' => 'files', 'icon' => 'folder'],
        ];
    }
@endphp

<x-layouts.app :title="__('assembly.title')">
    <x-page-hero :title="__('assembly.title')" />

    <section class="py-10 sm:py-14">
        <div class="container-page">
            @if ($tiles !== [])
                <h2 class="sr-only">{{ __('assembly.index.tiles') }}</h2>
                <ul class="grid grid-cols-2 gap-4 {{ count($tiles) === 4 ? 'lg:grid-cols-4' : 'sm:grid-cols-3' }}">
                    @foreach ($tiles as $tile)
                        <li>
                            <a href="{{ route($tile['route']) }}"
                                class="flex h-full flex-col items-center gap-3 rounded-2xl border border-line/70 bg-white p-6 text-center shadow-xs transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md">
                                <span class="text-primary" aria-hidden="true">{{ svg('heroicon-o-'.$tile['icon'], 'size-9') }}</span>
                                <span class="font-semibold text-ink">{{ __('assembly.index.'.$tile['label']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-alert type="info">
                    <p>{{ __('assembly.index.not_member') }}</p>
                    @if (Route::has('services.assembly-membership'))
                        <x-button class="mt-4" :href="route('services.assembly-membership')" arrow>{{ __('assembly.index.join') }}</x-button>
                    @endif
                </x-alert>
            @endif

            @if ($next)
                <div class="mt-10">
                    <x-section-heading>{{ __('assembly.index.next_meeting') }}</x-section-heading>
                    <div class="mt-6 max-w-md">
                        @include('assembly.meetings.card', ['meeting' => $next])
                    </div>
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
