{{--
    أسفل رئيسية اللوحة: أقرب البرامج المسجل بها، وآخر الطلبات، بطاقتان متجاورتان على الحاسوب
    ومتتاليتان على الجوال. لكل منهما رابط «عرض الكل».
--}}
@php
    use App\Support\Dates;
@endphp

<div class="grid gap-6 xl:grid-cols-2">
    <x-card>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-ink">{{ __('account.summary.upcoming') }}</h2>
            <a href="{{ route('account.programs.index') }}" class="text-sm text-primary underline decoration-gold underline-offset-4">{{ __('account.summary.view_all') }}</a>
        </div>

        @if ($summary['upcoming']->isEmpty())
            <p class="mt-4 text-muted">{{ __('account.summary.upcoming_empty') }}</p>
        @else
            <ul class="mt-4 divide-y divide-line/70">
                @foreach ($summary['upcoming'] as $registration)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <span class="min-w-0">
                            <a href="{{ route('programs.show', $registration->program->slug) }}" class="block font-semibold text-ink hover:text-primary">{{ $registration->program->title }}</a>
                            <span class="mt-1 flex items-center gap-1.5 text-sm text-muted">
                                {{ svg('heroicon-o-calendar', 'size-4 text-gold') }}
                                {{ Dates::display($registration->program->starts_at, withTime: true) }}
                            </span>
                        </span>
                        @include('account.requests.status', ['status' => $registration->status])
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-ink">{{ __('account.summary.latest_requests') }}</h2>
            <a href="{{ route('account.requests.index') }}" class="text-sm text-primary underline decoration-gold underline-offset-4">{{ __('account.summary.view_all') }}</a>
        </div>

        @if ($summary['latest_requests']->isEmpty())
            <p class="mt-4 text-muted">{{ __('account.summary.requests_empty') }}</p>
        @else
            <ul class="mt-4 divide-y divide-line/70">
                @foreach ($summary['latest_requests'] as $request)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <span class="min-w-0">
                            <a href="{{ route('account.requests.show', $request) }}" class="block font-semibold text-ink hover:text-primary">{{ $request->opening->title }}</a>
                            <span class="mt-1 block text-sm text-muted"><span dir="ltr">{{ $request->reference }}</span></span>
                        </span>
                        @include('account.requests.status', ['status' => $request->status])
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
