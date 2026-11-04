{{--
    بطاقة مسابقة الطلاب أعلى «حسابي» لكل مسابقة سجّل فيها (ملاحظات المدير التنفيذي، ليلة 4 نوفمبر 2026، المجموعة ج): اسمها، والمرحلة
    الحالية، وزر واحد كبير بحالته (BuildStudentCompetitionCards). والحالتان بلا إجراء (قادمة ومنتهية) شارة بنص الزر لا رابط.
--}}

@foreach ($cards as $card)
    <section class="tone-card flex flex-col gap-4 p-5.5 sm:flex-row sm:items-center sm:justify-between" data-account-competition="{{ $card['state'] }}"
        aria-labelledby="account-competition-{{ $card['competition']->id }}">
        <div class="flex items-center gap-4">
            <x-icon-tile icon="trophy" size="lg" />
            <div class="space-y-1">
                <h2 id="account-competition-{{ $card['competition']->id }}" class="text-xl font-black">{{ $card['competition']->title }}</h2>
                <p class="tone-card-muted font-semibold">{{ __('competitions.account_card.round', ['name' => $card['round']->name]) }}</p>
            </div>
        </div>

        @php
            $label = $card['state'] === 'upcoming'
                ? __('competitions.account_card.upcoming', ['date' => \App\Support\Dates::display($card['starts_at'], withTime: true)])
                : __('competitions.account_card.'.$card['state']);
        @endphp

        @if ($card['url'])
            <x-button :href="$card['url']" size="lg" :variant="$card['state'] === 'submitted' ? 'outline' : 'primary'" :arrow="$card['state'] !== 'submitted'"
                class="w-full sm:w-auto" data-account-competition-action>{{ $label }}</x-button>
        @else
            <p class="keep-color w-full rounded-full bg-surface px-7 py-3 text-center text-lg font-semibold text-muted sm:w-auto" data-account-competition-action>{{ $label }}</p>
        @endif
    </section>
@endforeach
