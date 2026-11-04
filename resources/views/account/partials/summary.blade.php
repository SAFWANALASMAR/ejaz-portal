{{--
    أسفل رئيسية اللوحة (Final-Dashboard): «مواعيدي القادمة» مربعات ملونة متجاورة (اليوم والشهر في مربع، ثم البرنامج، ثم الوقت
    وطريقة الحضور)، ثم آخر الطلبات. لكل منهما رابط «عرض الكل» متى كان فيه شيء، والفارغ بزر داخل حالته إلى حيث يبدأ (ملاحظة
    المقيّم على #167): المواعيد إلى البرامج، والطلبات إلى الخدمات الإلكترونية.
--}}
@php
    use App\Support\Dates;
@endphp

<section class="concept-panel rounded-[1.25rem] p-5 sm:p-6" aria-labelledby="account-upcoming" data-account-upcoming>
    <div class="flex items-center justify-between gap-3">
        <h2 id="account-upcoming" class="flex items-center gap-2.5 text-[1.375rem] font-black text-primary-dark sm:text-2xl">
            <x-icon-tile icon="calendar-days" size="sm" shade="dark" />
            {{ __('account.summary.upcoming') }}
        </h2>
        @if ($summary['upcoming']->isNotEmpty())
            <a href="{{ route('account.programs.index') }}" wire:navigate class="more-link shrink-0">{{ __('account.summary.view_all') }}<span class="sr-only">: {{ __('account.summary.upcoming') }}</span></a>
        @endif
    </div>

    @if ($summary['upcoming']->isEmpty())
        <x-empty-state icon="calendar-days" :message="__('account.summary.upcoming_empty')" class="mt-4">
            <x-button variant="outline" :href="route('programs.index')" arrow>{{ __('programs.mine.browse') }}</x-button>
        </x-empty-state>
    @else
        <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($summary['upcoming'] as $registration)
                @php($starts = Dates::local($registration->program->starts_at))
                <li class="tone-card flex min-h-[10.625rem] flex-col items-center justify-center gap-2.5 p-4 text-center" data-appointment>
                    <span class="acct-date flex size-15 flex-none flex-col items-center justify-center rounded-[0.875rem] leading-tight font-black">
                        <span class="text-xl tabular-nums">{{ $starts->translatedFormat('j') }}</span>
                        <span class="text-xs">{{ $starts->translatedFormat('F') }}</span>
                    </span>
                    @if ($registration->program->is_published)
                        <a href="{{ route('programs.show', $registration->program->slug) }}" wire:navigate class="text-lg font-extrabold hover:underline">{{ $registration->program->title }}</a>
                    @else
                        <span class="text-lg font-extrabold">{{ $registration->program->title }}</span>
                    @endif
                    <span class="tone-card-muted text-sm">{{ Dates::display($registration->program->starts_at, withTime: true) }} — {{ $registration->program->delivery->getLabel() }}</span>
                    <span class="keep-color">@include('account.requests.status', ['status' => $registration->status])</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section class="concept-panel rounded-[1.25rem] p-5 sm:p-6" aria-labelledby="account-latest-requests">
    <div class="flex items-center justify-between gap-3">
        <h2 id="account-latest-requests" class="flex items-center gap-2.5 text-[1.375rem] font-black text-primary-dark sm:text-2xl">
            <x-icon-tile icon="clipboard-document-list" size="sm" shade="medium" />
            {{ __('account.summary.latest_requests') }}
        </h2>
        @if ($summary['latest_requests']->isNotEmpty())
            <a href="{{ route('account.requests.index') }}" wire:navigate class="more-link shrink-0">{{ __('account.summary.view_all') }}<span class="sr-only">: {{ __('account.summary.latest_requests') }}</span></a>
        @endif
    </div>

    @if ($summary['latest_requests']->isEmpty())
        <x-empty-state icon="clipboard-document-list" :message="__('account.summary.requests_empty')" class="mt-4">
            @if (Route::has('services.index'))
                <x-button variant="outline" :href="route('services.index')" arrow>{{ __('services.requests.browse') }}</x-button>
            @endif
        </x-empty-state>
    @else
        <ul class="mt-4 divide-y divide-line/70">
            @foreach ($summary['latest_requests'] as $request)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <span class="min-w-0">
                        <a href="{{ route('account.requests.show', $request) }}" wire:navigate class="block font-semibold text-ink hover:text-primary">{{ $request->opening->title }}</a>
                        <span class="mt-1 block text-sm text-muted"><span dir="ltr">{{ $request->reference }}</span></span>
                    </span>
                    @include('account.requests.status', ['status' => $request->status])
                </li>
            @endforeach
        </ul>
    @endif
</section>
