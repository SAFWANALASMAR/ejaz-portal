{{--
    «قراءاتي ومسموعاتي» في رئيسية اللوحة (Final-Dashboard — البند D1): مجاميع صاحب الحساب في ثلاث بطاقات ملونة، ثم أحدث ما بدأه
    بتقدّمه وزر المتابعة، و«عرض الكل» إلى صفحة «قراءاتي». وبلا تقدّم: دعوة إلى المكتبة. بياناته وحده (BuildMyReading).
--}}
<section class="concept-panel rounded-[1.25rem] p-5 sm:p-6" aria-labelledby="account-my-reading" data-account-my-reading>
    <div class="flex items-center justify-between gap-3">
        <h2 id="account-my-reading" class="flex items-center gap-2.5 text-[1.375rem] font-black text-primary-dark sm:text-2xl">
            <x-icon-tile icon="book-open" size="sm" shade="medium" />
            {{ __('reading.title') }}
        </h2>
        <a href="{{ route('account.my-reading') }}" wire:navigate class="more-link shrink-0">{{ __('account.summary.view_all') }}<span class="sr-only">: {{ __('reading.title') }}</span></a>
    </div>
    <p class="mt-1 text-sm text-muted" data-reading-tracking-note="{{ $trackingDisabled ? 'off' : 'on' }}">{{ __($trackingDisabled ? 'reading.tracking.off_note' : 'reading.description') }}</p>

    @if ($reading['latest']->isEmpty())
        <x-empty-state icon="book-open" :message="__('reading.empty')" class="mt-4">
            <x-button variant="outline" :href="route('library.index')" arrow>{{ __('reading.browse') }}</x-button>
        </x-empty-state>
    @else
        @include('account.partials.reading-totals', ['totals' => $reading['totals']])

        <ul class="mt-4 grid gap-3">
            @foreach ($reading['latest'] as $progress)
                @include('account.partials.reading-item', ['progress' => $progress])
            @endforeach
        </ul>
    @endif
</section>
