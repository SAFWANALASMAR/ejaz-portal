{{--
    رئيسية المشرف العام (ملاحظات المدير التنفيذي، 25 أكتوبر 2026): اجتماع تيمز القادم إن وُجد، وإحصائيات عامة للاطلاع من
    المحسوب في الخلفية (App\Actions\Accounts\BuildOversight).
--}}
@if ($oversight['meeting'])
    <div class="flex flex-col gap-4 rounded-2xl border border-primary/30 bg-primary-soft p-6 shadow-xs sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-4">
            <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                {{ \App\Support\Icon::svg('video-camera', 'size-6') }}
            </span>
            <div>
                <p class="text-sm font-semibold text-accent-dark">{{ __('account.oversight.next_meeting', ['body' => $oversight['meeting']->committee?->name ?? $oversight['meeting']->body->getLabel()]) }}</p>
                <h2 class="mt-1 text-lg font-bold text-ink">{{ $oversight['meeting']->title }}</h2>
                <p class="mt-1 text-sm text-muted">{{ \App\Support\Dates::display($oversight['meeting']->starts_at, withTime: true) }}</p>
            </div>
        </div>
        <x-button :href="$oversight['meeting']->online_join_url" target="_blank" rel="noopener noreferrer" arrow class="shrink-0">
            {{ __('account.oversight.join_teams') }}
            <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
        </x-button>
    </div>
@endif

@if ($oversight['stats'] !== null)
    <x-card>
        <h2 class="text-lg font-semibold text-ink">{{ __('account.oversight.title') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ __('account.oversight.description') }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach (['programs' => 'academic-cap', 'registrations' => 'clipboard-document-check', 'members' => 'user-group', 'meetings' => 'calendar-days', 'submissions' => 'trophy'] as $key => $icon)
                <div class="rounded-xl border border-line/70 p-4">
                    <dt class="flex items-center gap-2 text-sm text-muted">
                        {{ \App\Support\Icon::svg($icon, 'size-5 shrink-0 text-accent') }}
                        {{ __('account.oversight.'.$key) }}
                    </dt>
                    <dd class="mt-2 text-2xl font-bold text-ink">{{ number_format($oversight['stats'][$key]) }}</dd>
                </div>
            @endforeach
        </dl>
    </x-card>
@endif
