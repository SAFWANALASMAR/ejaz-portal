{{--
    المحاضر المعتمدة في منطقة الأعضاء: لكل محضر اجتماعه وتاريخ اعتماده ورابط تنزيل مؤقت موقّع.
--}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.app :title="__('assembly.minutes.title')" noindex>
    <section class="band-sky py-12 sm:py-16">
        <div class="container-page max-w-4xl">
            <span class="block h-1 w-12 rounded-full bg-accent" aria-hidden="true"></span>
            <h1 class="mt-3 text-3xl font-bold text-primary sm:text-4xl">{{ __('assembly.minutes.title') }}</h1>

            @if ($meetings->isEmpty())
                <x-empty-state icon="document-text" :message="__('assembly.minutes.empty')" class="mt-6" />
            @else
                <ul class="mt-6 divide-y divide-line/70 overflow-hidden concept-panel">
                    @foreach ($meetings as $meeting)
                        <li class="flex flex-wrap items-center gap-4 p-4 sm:p-5">
                            <span class="flex size-11 shrink-0 flex-col items-center justify-center rounded-xl bg-primary-soft text-primary" aria-hidden="true">
                                {{ \App\Support\Icon::svg('document-text', 'size-6') }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <a href="{{ route('assembly.meetings.show', $meeting) }}" wire:navigate class="block font-semibold text-ink hover:text-primary">{{ $meeting->title }}</a>
                                <span class="mt-1 block text-sm text-muted">
                                    {{ Dates::display($meeting->starts_at) }} — {{ __('assembly.minutes.approved_on', ['date' => Dates::display($meeting->minutes_approved_at)]) }}
                                </span>
                            </span>
                            <x-button variant="outline" size="sm" :href="$meeting->minutesUrl()">{{ __('assembly.minutes.download') }}</x-button>
                        </li>
                    @endforeach
                </ul>

                {{ $meetings->links('partials.pagination') }}
            @endif
        </div>
    </section>
</x-layouts.app>
