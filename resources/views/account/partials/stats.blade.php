{{-- إحصائيات المشترك (requirements/12 §12 د): برامجه المسجل فيها والمنجزة، ومشاركاته في المسابقات كما في «مشاركاتي» (BuildParticipationStats)، ببطاقات ملونة تتعاقب (.tone-card — Final-Dashboard «إحصائياتي») --}}
<x-card>
    <h2 class="flex items-center gap-2 text-lg font-black text-primary-dark"><span class="heading-flank-dot" aria-hidden="true"></span>{{ __('portals.member.stats.title') }}</h2>
    <p class="mt-1 text-sm text-muted">{{ __('portals.member.stats.description') }}</p>
    <dl class="mt-4 grid gap-4 sm:grid-cols-3">
        @foreach (['registered' => 'academic-cap', 'completed' => 'check-badge', 'entries' => 'trophy'] as $key => $icon)
            <div class="tone-card grid grid-cols-[auto_1fr] items-center gap-x-3 p-4" data-stat-card>
                <dt class="tone-card-muted contents text-sm"><x-icon-tile :icon="$icon" size="sm" :index="$loop->index" class="row-span-2" /><span>{{ $key === 'entries' ? __('portals.member_entries') : __('portals.member.stats.'.$key) }}</span></dt>
                <dd class="acct-number font-black tabular-nums">{{ number_format($stats[$key]) }}</dd>
            </div>
        @endforeach
    </dl>
</x-card>
