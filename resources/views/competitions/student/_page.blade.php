{{--
    صفحة مسابقة الطلاب من اللوحة (مطوية «البراهين العلمية») بترتيب «قواعد التنسيق» (plans/2026-10-30-work-queue.md):
    الجهة المنسَّق معها، ثم المجالات والمراحل ظاهرة، ثم جدول الجوائز، ثم الأقسام النصية بطاقات مطوية، ثم دليل الدخول
    شرائح. وما لم يُكتب لا يظهر.
--}}
@php
    use App\Support\CompetitionPage;
    use Illuminate\Support\Number;

    $prizes = $competition->prizeRows();
    $rounds = $competition->rounds()->get();
    // مجالات المسابقة من مجال كل مرحلة (competition_rounds.branch) بترتيبها، لقسمها ولبطاقات المراحل
    $domains = $rounds->pluck('branch')->filter(fn (?string $branch): bool => filled($branch))->unique()->values();

    // الأقسام المطوية بترتيبها وأيقوناتها (Heroicons، ويحوّلها Icon::svg إلى Phosphor بعد #95). والفئات من الفئات
    // المفتوحة في المسابقة، وشرط المشاركة في كل المراحل في الضوابط
    $icons = [
        'intro' => 'heroicon-o-document-text', 'idea' => 'heroicon-o-light-bulb', 'vision' => 'heroicon-o-eye',
        'mission' => 'heroicon-o-paper-airplane', 'goal' => 'heroicon-o-flag', 'goals' => 'heroicon-o-check-badge',
        'outcomes' => 'heroicon-o-presentation-chart-line', 'categories' => 'heroicon-o-academic-cap', 'rules' => 'heroicon-o-scale',
        'schools' => 'heroicon-o-building-library', 'guide_before' => 'heroicon-o-clipboard-document-check',
        'guide_alerts' => 'heroicon-o-exclamation-triangle', 'guide_problems' => 'heroicon-o-lifebuoy',
    ];
    $sections = [];

    foreach (CompetitionPage::PARAGRAPHS as $key) {
        if ($text = $competition->pageText($key)) {
            $sections[$key] = ['text' => $text];
        }
    }

    foreach (CompetitionPage::LISTS as $key) {
        if ($key === 'rules') {
            $categories = $competition->openCategories()->pluck('name')->all();

            if ($categories !== []) {
                $sections['categories'] = ['items' => $categories];
            }
        }

        $items = $competition->pageList($key);
        $note = $key === 'rules' && $rounds->count() > 1 ? __('competitions.student.page.all_branches') : null;

        if ($items !== [] || $note !== null) {
            $sections[$key] = ['items' => $items, 'note' => $note];
        }
    }

    foreach (['guide_before', 'guide_alerts', 'guide_problems'] as $key) {
        if (($items = $competition->pageList($key)) !== []) {
            $sections[$key] = ['items' => $items];
        }
    }

    $steps = $competition->pageList('guide_steps');
@endphp

@include('competitions.student._domains')
@include('competitions.student._rounds')

@if ($prizes !== [])
    <div>
        <x-section-heading>{{ __('competitions.student.page.prizes') }}</x-section-heading>
        <div class="card-lift mt-4 overflow-x-auto">
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
        <p class="mt-3 font-bold text-primary">{{ __('competitions.student.page.prizes_grand_total', ['amount' => Number::format(array_sum(array_column($prizes, 'male')) + array_sum(array_column($prizes, 'female')))]) }}</p>
    </div>
@endif

@if ($sections !== [])
    {{-- details/summary: المحتوى في الصفحة لمحركات البحث ويُفتح بلوحة المفاتيح بلا سكربت، و«عرض الكل» بالسكربت وحده --}}
    <div class="space-y-3" data-collapsible-group>
        <div class="flex justify-end">
            <button type="button" hidden data-collapsible-all aria-expanded="false" aria-controls="{{ collect(array_keys($sections))->map(fn (string $key): string => 'page-section-'.$key)->implode(' ') }}" data-show="{{ __('competitions.student.page.show_all') }}" data-hide="{{ __('competitions.student.page.hide_all') }}"
                class="inline-flex items-center gap-2 rounded-full border border-primary/30 px-4 py-1.5 text-sm font-semibold text-primary transition hover:bg-primary-soft">
                {{ svg('heroicon-o-chevron-double-down', 'size-4', ['aria-hidden' => 'true']) }}
                <span data-collapsible-all-label>{{ __('competitions.student.page.show_all') }}</span>
            </button>
        </div>

        @foreach ($sections as $key => $section)
            <x-competitions.collapsible :index="$loop->index" :id="'page-section-'.$key" :title="__('competitions.student.page.'.$key)" :icon="$icons[$key]">
                @isset($section['text'])
                    <p class="leading-8 whitespace-pre-line text-ink">{{ $section['text'] }}</p>
                @endisset
                @if (($section['items'] ?? []) !== [])
                    <ul class="list-disc space-y-2 ps-6 leading-8 text-ink">
                        @foreach ($section['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
                @if ($section['note'] ?? null)
                    <p class="mt-3 text-sm leading-7 text-muted">{{ $section['note'] }}</p>
                @endif
            </x-competitions.collapsible>
        @endforeach
    </div>
@endif

@if ($steps !== [])
    <div>
        <x-section-heading>{{ __('competitions.student.page.guide_steps') }}</x-section-heading>
        <x-competitions.guide-slides :steps="$steps" class="mt-4" />
    </div>
@endif

@if ($competition->hasGuide())
    <x-button :href="route('competitions.student.guide', $competition->slug)" variant="outline" arrow>{{ __('competitions.student.page.guide_link') }}</x-button>
@endif
