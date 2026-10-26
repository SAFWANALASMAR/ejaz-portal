{{--
    نصوص صفحة مسابقة الطلاب من اللوحة (مطوية «البراهين العلمية»): الجهة المنسَّق معها، والفقرات، والقوائم، وجدول
    الجوائز لكل جنس، ورابط دليل الدخول. وما لم يُكتب لا يظهر.
--}}
@php
    use App\Support\CompetitionPage;
    use Illuminate\Support\Number;

    $prizes = $competition->prizeRows();
@endphp

@if ($partner = $competition->pageText(CompetitionPage::PARTNER))
    <p class="text-sm text-muted">{{ __('competitions.student.page.partner') }}: <span class="font-semibold text-ink">{{ $partner }}</span></p>
@endif

@foreach (CompetitionPage::PARAGRAPHS as $key)
    @if ($text = $competition->pageText($key))
        <div>
            <x-section-heading>{{ __('competitions.student.page.'.$key) }}</x-section-heading>
            <p class="mt-4 leading-8 whitespace-pre-line text-ink">{{ $text }}</p>
        </div>
    @endif
@endforeach

@foreach (CompetitionPage::LISTS as $key)
    @php($items = $competition->pageList($key))
    @if ($items !== [])
        <div>
            <x-section-heading>{{ __('competitions.student.page.'.$key) }}</x-section-heading>
            <ul class="mt-4 list-disc space-y-2 ps-6 leading-8 text-ink">
                @foreach ($items as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @endif
@endforeach

@if ($prizes !== [])
    <div>
        <x-section-heading>{{ __('competitions.student.page.prizes') }}</x-section-heading>
        <div class="mt-4 overflow-x-auto rounded-2xl border border-line/70 bg-white shadow-xs">
            <table class="w-full text-start text-sm">
                <thead class="bg-primary-soft text-primary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-bold">{{ __('competitions.student.page.place') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-bold">{{ __('competitions.student.gender_groups.male') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-bold">{{ __('competitions.student.gender_groups.female') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line/70">
                    @foreach ($prizes as $prize)
                        <tr>
                            <th scope="row" class="px-4 py-2.5 text-start font-semibold text-ink">{{ __('competitions.student.page.places')[$prize['place']] ?? Number::format($prize['place']) }}</th>
                            <td class="px-4 py-2.5 text-ink">{{ __('competitions.student.page.currency', ['amount' => Number::format($prize['male'])]) }}</td>
                            <td class="px-4 py-2.5 text-ink">{{ __('competitions.student.page.currency', ['amount' => Number::format($prize['female'])]) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-primary-soft font-bold text-primary">
                    <tr>
                        <th scope="row" class="px-4 py-3 text-start">{{ __('competitions.student.page.prizes_total') }}</th>
                        <td class="px-4 py-3">{{ __('competitions.student.page.currency', ['amount' => Number::format(array_sum(array_column($prizes, 'male')))]) }}</td>
                        <td class="px-4 py-3">{{ __('competitions.student.page.currency', ['amount' => Number::format(array_sum(array_column($prizes, 'female')))]) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif

@if ($competition->hasGuide())
    <x-button :href="route('competitions.student.guide', $competition->slug)" variant="outline" arrow>{{ __('competitions.student.page.guide_link') }}</x-button>
@endif
