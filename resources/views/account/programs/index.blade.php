{{-- «البرامج المسجل بها»: تسجيلات المستخدم بحالاتها، وإلغاء التسجيل قبل بدء البرنامج --}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.account :title="__('programs.mine.title')">
    @if ($registrations->isEmpty())
        <x-card>
            <p class="text-muted">{{ __('programs.mine.empty') }}</p>
            <x-button class="mt-4" variant="outline" :href="route('programs.index')" arrow>{{ __('programs.mine.browse') }}</x-button>
        </x-card>
    @else
        <ul class="space-y-3">
            @foreach ($registrations as $registration)
                <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line/70 bg-white p-5 shadow-xs">
                    <div class="min-w-0">
                        <a href="{{ route('programs.show', $registration->program->slug) }}" class="block font-semibold text-ink hover:text-primary">{{ $registration->program->title }}</a>
                        <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                            <span>{{ $registration->program->type->name }}</span>
                            <span>{{ Dates::display($registration->program->starts_at, withTime: true) }}</span>
                            @if ($registration->attended_at)
                                <span class="text-primary">{{ __('programs.mine.attended') }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        @include('account.requests.status', ['status' => $registration->status])
                        @can('cancel', $registration)
                            <form method="POST" action="{{ route('account.programs.cancel', $registration) }}" data-confirm="{{ __('programs.mine.cancel_confirm') }}">
                                @csrf
                                <x-button variant="link" class="text-sm">{{ __('programs.mine.cancel') }}</x-button>
                            </form>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>

        {{ $registrations->links('partials.pagination') }}
    @endif
</x-layouts.account>
