{{-- «البرامج المسجل بها»: تسجيلات المستخدم بحالاتها، وإلغاء التسجيل قبل بدء البرنامج --}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.account :title="__('programs.mine.title')">
    @if ($registrations->isEmpty())
        <x-card>
            <x-empty-state icon="academic-cap" :message="__('programs.mine.empty')" />
            <x-button class="mt-4" variant="outline" :href="route('programs.index')" arrow>{{ __('programs.mine.browse') }}</x-button>
        </x-card>
    @else
        <ul class="space-y-3">
            @foreach ($registrations as $registration)
                <li class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line/70 bg-white p-5 shadow-xs">
                    <div class="min-w-0">
                        @if ($registration->program->is_published && ! $registration->program->trashed())
                            <a href="{{ route('programs.show', $registration->program->slug) }}" wire:navigate class="block font-semibold text-ink hover:text-primary">{{ $registration->program->title }}</a>
                        @else
                            <span class="block font-semibold text-ink">{{ $registration->program->title }}</span>
                        @endif
                        <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                            <span>{{ $registration->program->type->name }}</span>
                            <span>{{ Dates::display($registration->program->starts_at, withTime: true) }}</span>
                            @if ($registration->attended_at)
                                <span class="text-primary">{{ __('programs.mine.attended') }}</span>
                            @endif
                            @if ($registration->certificate_path)
                                <a href="{{ route('account.programs.certificate', $registration) }}" class="inline-flex items-center gap-1 text-primary underline decoration-accent underline-offset-4">
                                    {{ \App\Support\Icon::svg('arrow-down-tray', 'size-4') }}
                                    {{ __('programs.certificate.download') }}
                                </a>
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
