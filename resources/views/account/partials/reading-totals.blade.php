{{-- مجاميع «قراءاتي» لصاحب الحساب: الصفحات المقروءة، ودقائق الاستماع والمشاهدة، ببطاقات ملونة تتعاقب (.tone-card) --}}
<dl class="mt-4 grid gap-3 sm:grid-cols-3" aria-label="{{ __('reading.totals.label') }}" data-reading-totals>
    @foreach (['pages_read' => 'book-open', 'listened_minutes' => 'musical-note', 'watched_minutes' => 'play-circle'] as $key => $icon)
        <div class="tone-card grid grid-cols-[auto_1fr] items-center gap-x-3 p-4">
            <dt class="tone-card-muted contents text-sm"><x-icon-tile :icon="$icon" size="sm" class="row-span-2" /><span>{{ __('reading.totals.'.$key) }}</span></dt>
            <dd class="text-2xl font-black tabular-nums">{{ number_format($totals[$key]) }}</dd>
        </div>
    @endforeach
</dl>
