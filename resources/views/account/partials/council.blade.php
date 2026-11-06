{{--
    بوابة عضو مجلس الإدارة في «حسابي» (ملاحظات المدير التنفيذي، ليلة 5 نوفمبر 2026، البند ٤ — App\Actions\Accounts\BuildCouncilPortal):
    صف بطاقات للمجلس، ثم «إحصائيات الموقع» مجمّعة. والإحصائيات لعضو الجمعية العمومية أيضاً. ولوحة الإدارة تحميلاً كاملاً.
--}}
@if ($council['cards'] !== [])
    <section aria-labelledby="council-title" class="space-y-4" data-council-cards>
        <h2 id="council-title" class="text-lg font-semibold text-ink">{{ __('account.council.title') }}</h2>
        <ul class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
            @foreach ($council['cards'] as $card)
                <li class="flex odd:last:col-span-2 lg:odd:last:col-span-1">
                    <a href="{{ $card['url'] }}" data-council-card="{{ $card['key'] }}"
                        @if ($card['external']) target="_blank" rel="noopener noreferrer" @elseif ($card['navigate']) @navigate($card['url']) @endif
                        class="tone-card group flex w-full flex-col gap-2 p-4 sm:p-5">
                        <x-icon-tile :icon="['stats' => 'chart-bar', 'assembly_members' => 'user-group', 'board' => 'building-library', 'live' => 'video-camera', 'governance' => 'document-text'][$card['key']]" size="md" />
                        <span class="text-base font-black sm:text-lg">{{ __('account.council.cards.'.$card['key']) }}</span>
                        <span class="acct-arrow mt-auto inline-flex size-[2.375rem] items-center justify-center self-end rounded-full" aria-hidden="true">
                            {{ \App\Support\Icon::svg($card['external'] ? 'arrow-up-left' : 'arrow-left', 'size-4') }}
                        </span>
                        @if ($card['external'])
                            <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif

@if ($council['stats'] !== null && $council['stats'] !== [])
    <x-card id="site-stats" class="scroll-mt-28" data-site-stats>
        <h2 class="text-lg font-semibold text-ink">{{ __('account.council.stats_title') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ __('account.council.stats_description') }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
            @foreach ($council['stats'] as $key => $value)
                <div class="tone-card p-4">
                    <dt class="tone-card-muted flex items-center gap-2 text-sm">
                        <x-icon-tile :icon="['visits' => 'eye', 'book_reads' => 'book-open', 'media_views' => 'play-circle', 'registrants' => 'trophy', 'programs' => 'academic-cap', 'members' => 'user-group'][$key]" size="xs" />
                        {{ __('account.council.stats.'.$key) }}
                    </dt>
                    <dd class="acct-number mt-2 font-black tabular-nums">{{ number_format($value) }}</dd>
                </div>
            @endforeach
        </dl>
    </x-card>
@endif
