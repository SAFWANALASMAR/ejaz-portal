{{--
    مجالات المسابقة ($domains من _page): بطاقة مرقمة لكل مجال من مجالات المراحل (competition_rounds.branch) بترتيبها، «المجال الأول…». والأيقونات
    بترتيب مجالات «البراهين» (الأنفس، والفلك، والأرض، والبحار)، وما زاد يأخذ الأخيرة (Heroicons، ويحوّلها Icon::svg
    إلى Phosphor بعد #95).
--}}
@php
    use Illuminate\Support\Number;

    $domainIcons = ['heroicon-o-finger-print', 'heroicon-o-moon', 'heroicon-o-globe-europe-africa', 'heroicon-o-lifebuoy', 'heroicon-o-beaker'];
@endphp

@if ($domains->isNotEmpty())
    <div data-domains>
        <x-section-heading>{{ __('competitions.student.page.domains') }}</x-section-heading>
        <ol class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ($domains as $index => $domain)
                <li class="flex items-center gap-4 rounded-2xl border border-line/70 bg-white p-4 shadow-xs">
                    <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-primary-soft text-brand" aria-hidden="true">
                        {{ svg($domainIcons[min($index, count($domainIcons) - 1)], 'size-7') }}
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-accent">{{ __('competitions.student.page.domain', ['ordinal' => __('competitions.student.page.places')[$index + 1] ?? Number::format($index + 1)]) }}</p>
                        <p class="font-bold leading-7 text-ink">{{ $domain }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
@endif
