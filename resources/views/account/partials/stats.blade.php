{{-- إحصائيات المشترك (requirements/12 §12 د): برامجه المسجل فيها والمنجزة، ومشاركاته المرسلة (BuildParticipationStats)، ببلاطات متدرجة بالترتيب --}}
<x-card>
    <h2 class="flex items-center gap-2 text-lg font-black text-primary-dark"><span class="heading-flank-dot" aria-hidden="true"></span>{{ __('portals.member.stats.title') }}</h2>
    <p class="mt-1 text-sm text-muted">{{ __('portals.member.stats.description') }}</p>
    <dl class="mt-4 grid gap-4 sm:grid-cols-3">
        @foreach (['registered' => 'academic-cap', 'completed' => 'check-badge', 'entries' => 'trophy'] as $key => $icon)
            <div class="card-lift grid grid-cols-[auto_1fr] items-center gap-x-3 p-4" data-stat-card>
                <dt class="contents text-sm text-muted"><x-icon-tile :icon="$icon" size="sm" :index="$loop->index" class="row-span-2" /><span>{{ __('portals.member.stats.'.$key) }}</span></dt>
                <dd class="text-2xl font-black text-primary-dark tabular-nums">{{ number_format($stats[$key]) }}</dd>
            </div>
        @endforeach
    </dl>
</x-card>
