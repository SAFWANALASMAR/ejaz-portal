{{-- إحصائيات المشترك (requirements/12 §12 د): برامجه المسجل فيها والمنجزة، ومشاركاته المرسلة (BuildParticipationStats) --}}
<x-card>
    <h2 class="text-lg font-semibold text-ink">{{ __('portals.member.stats.title') }}</h2>
    <p class="mt-1 text-sm text-muted">{{ __('portals.member.stats.description') }}</p>
    <dl class="mt-4 grid gap-4 sm:grid-cols-3">
        @foreach (['registered', 'completed', 'entries'] as $key)
            <div class="rounded-xl border border-line/70 p-4">
                <dt class="text-sm text-muted">{{ __('portals.member.stats.'.$key) }}</dt>
                <dd class="mt-1 text-2xl font-bold text-ink">{{ number_format($stats[$key]) }}</dd>
            </div>
        @endforeach
    </dl>
</x-card>
