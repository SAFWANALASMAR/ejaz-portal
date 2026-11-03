{{--
    «قراءاتي» (docs/decisions/2026-11-01-my-reading.md): مجاميع صاحب الحساب، ثم الكتب والأبحاث بالصفحات المقروءة من عددها، ثم
    الصوتيات، ثم المرئيات، لكل عنصر تقدّمه وزر المتابعة من موضعه. والعناصر المنشورة وحدها، وبياناته وحده (BuildMyReading).
--}}
<x-layouts.account :title="__('reading.nav')">
    {{-- الحفظ تلقائي افتراضياً، ويوقفه صاحب الحساب من «بياناتي» (قرار صفوان، 2 نوفمبر 2026) --}}
    <p class="text-sm text-muted" data-reading-tracking-note="{{ $trackingDisabled ? 'off' : 'on' }}">
        {{ __($trackingDisabled ? 'reading.tracking.off_note' : 'reading.tracking.on_note') }}
        <a href="{{ route('account.profile') }}#reading-tracking" wire:navigate class="font-semibold text-primary underline decoration-accent underline-offset-4">{{ __('reading.tracking.settings') }}</a>
    </p>

    @if ($latest->isEmpty())
        <x-card>
            <x-empty-state icon="book-open" :message="__('reading.empty')">
                <x-button variant="outline" :href="route('library.index')" arrow>{{ __('reading.browse') }}</x-button>
            </x-empty-state>
        </x-card>
    @else
        <section class="concept-panel rounded-[1.25rem] p-5 sm:p-6" aria-labelledby="my-reading-totals">
            <h2 id="my-reading-totals" class="flex items-center gap-2.5 text-[1.375rem] font-black text-primary-dark sm:text-2xl">
                <x-icon-tile icon="chart-bar" size="sm" shade="dark" />
                {{ __('reading.title') }}
            </h2>
            <p class="mt-1 text-sm text-muted">{{ __('reading.description') }}</p>
            @include('account.partials.reading-totals', ['totals' => $totals])
        </section>

        @foreach (['books' => ['book-open', $books], 'audio' => ['musical-note', $audio], 'videos' => ['play-circle', $videos]] as $section => [$icon, $rows])
            <section class="concept-panel rounded-[1.25rem] p-5 sm:p-6" aria-labelledby="my-reading-{{ $section }}" data-reading-section="{{ $section }}">
                <h2 id="my-reading-{{ $section }}" class="flex items-center gap-2.5 text-[1.375rem] font-black text-primary-dark sm:text-2xl">
                    <x-icon-tile :icon="$icon" size="sm" shade="medium" />
                    {{ __('reading.sections.'.$section) }}
                </h2>
                @if ($rows->isEmpty())
                    <p class="mt-4 text-muted">{{ __('reading.section_empty') }}</p>
                @else
                    <ul class="mt-4 grid gap-3">
                        @foreach ($rows as $progress)
                            @include('account.partials.reading-item', ['progress' => $progress])
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach

        {{-- صاحب الحساب يمسح سجله متى شاء (docs/decisions/2026-11-01-my-reading.md — الخصوصية) --}}
        {{-- وتقدّم برامج المكتبة غير المُتمّة يعود إلى الصفر معه، فيُنبَّه قبل المسح (docs/decisions/2026-11-02-reading-program.md) --}}
        <form method="POST" action="{{ route('account.my-reading.clear') }}" data-confirm="{{ __('reading.clear_confirm') }} {{ __('reading.clear_programs_warning') }}"
            class="flex flex-wrap items-center justify-between gap-3 concept-panel rounded-[1.25rem] p-5 sm:p-6" data-reading-clear>
            @csrf
            <p class="text-sm text-muted">{{ __('reading.clear_hint') }} <span data-reading-clear-programs>{{ __('reading.clear_programs_warning') }}</span></p>
            <x-button variant="outline">{{ __('reading.clear') }}</x-button>
        </form>
    @endif
</x-layouts.account>
