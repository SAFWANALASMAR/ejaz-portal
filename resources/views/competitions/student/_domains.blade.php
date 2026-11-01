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
        <ol class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-reveal-group>
            @foreach ($domains as $index => $domain)
                <li class="tone-card group flex items-center gap-4 p-4.5">
                    <x-icon-tile :icon="str_replace('heroicon-o-', '', $domainIcons[min($index, count($domainIcons) - 1)])" />
                    <div>
                        <p class="tone-card-muted text-sm font-extrabold">{{ __('competitions.student.page.domain', ['ordinal' => __('competitions.student.page.places')[$index + 1] ?? Number::format($index + 1)]) }}</p>
                        <p class="text-lg leading-7 font-black">{{ $domain }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
@endif
