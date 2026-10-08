{{--
    المحاضر المعتمدة في منطقة الأعضاء: لكل محضر اجتماعه وتاريخ اعتماده ورابط تنزيل مؤقت موقّع.
--}}
@php
    use App\Support\Dates;
@endphp

<x-layouts.app :title="__('assembly.minutes.title')" noindex>
    <section class="bg-sand/60 py-12 sm:py-16">
        <div class="container-page max-w-4xl">
            <x-section-heading>{{ __('assembly.minutes.title') }}</x-section-heading>

            @if ($meetings->isEmpty())
                <p class="mt-6 text-muted">{{ __('assembly.minutes.empty') }}</p>
            @else
                <ul class="mt-6 divide-y divide-line/70 overflow-hidden rounded-2xl border border-line/70 bg-white shadow-xs">
                    @foreach ($meetings as $meeting)
                        <li class="flex flex-wrap items-center gap-4 p-4 sm:p-5">
                            <span class="flex size-11 shrink-0 flex-col items-center justify-center rounded-xl bg-primary-soft text-primary" aria-hidden="true">
                                {{ svg('heroicon-o-document-text', 'size-6') }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <a href="{{ route('assembly.meetings.show', $meeting) }}" class="block font-semibold text-ink hover:text-primary">{{ $meeting->title }}</a>
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
