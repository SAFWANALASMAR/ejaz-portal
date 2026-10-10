{{--
    صفحة الاجتماع في منطقة الأعضاء: الموعد والمكان أو تيمز (والرابط للأعضاء وحدهم ما لم يُلغَ)، وجدول
    الأعمال، والمرفقات، والحضور والنصاب بعد تسجيله، والمحضر بعد اعتماده. والتنزيل برابط مؤقت موقّع.
--}}
@php
    use App\Enums\MeetingStatus;
    use App\Support\Dates;

    $cancelled = $meeting->status === MeetingStatus::Cancelled;
    $quorum = $meeting->members_count !== null ? $meeting->quorumMet($attendees) : null;
@endphp

<x-layouts.app :title="$meeting->title" noindex>
    <section class="bg-surface/60 py-12 sm:py-16">
        <div class="container-page max-w-3xl">
            <a href="{{ route('assembly.meetings.index') }}" class="text-sm text-primary underline decoration-accent underline-offset-4">{{ __('assembly.meetings.back') }}</a>

            <x-card class="mt-6">
                <p class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-surface px-3 py-1 font-semibold text-muted">{{ __('assembly.meeting.model') }} {{ $meeting->type->getLabel() }}</span>
                    <span class="rounded-full px-3 py-1 font-semibold {{ match ($meeting->status) { MeetingStatus::Held => 'bg-primary text-white', MeetingStatus::Cancelled => 'bg-gray-500/90 text-white', default => 'bg-primary-soft text-primary' } }}">{{ $meeting->status->getLabel() }}</span>
                </p>
                <h1 class="mt-3 text-2xl font-bold text-primary sm:text-3xl">{{ $meeting->title }}</h1>

                @if ($cancelled)
                    <x-alert type="warning" class="mt-4">{{ __('assembly.meetings.cancelled_notice') }}</x-alert>
                @endif

                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-muted">{{ __('assembly.meetings.when') }}</dt>
                        <dd class="mt-1 flex items-center gap-2 font-semibold text-ink">{{ svg('heroicon-o-calendar-days', 'size-5 text-accent') }} {{ Dates::display($meeting->starts_at, withTime: true) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted">{{ __('assembly.meetings.where') }}</dt>
                        <dd class="mt-1 space-y-1 font-semibold text-ink">
                            @if (filled($meeting->location))
                                <span class="flex items-center gap-2">{{ svg('heroicon-o-map-pin', 'size-5 text-accent') }} {{ $meeting->location }}</span>
                            @endif
                            @if ($meeting->is_online)
                                <span class="flex items-center gap-2">{{ svg('heroicon-o-video-camera', 'size-5 text-accent') }} {{ __('assembly.meetings.online') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($meeting->is_online && ! $cancelled)
                    <div class="mt-6">
                        @if (filled($meeting->online_join_url))
                            <x-button :href="$meeting->online_join_url" target="_blank" rel="noopener noreferrer" arrow>{{ __('assembly.meetings.join') }}</x-button>
                        @else
                            <p class="text-sm text-muted">{{ __('assembly.meetings.link_pending') }}</p>
                        @endif
                    </div>
                @endif
            </x-card>

            @if (filled($meeting->agenda))
                <x-card class="mt-6">
                    <h2 class="text-lg font-bold text-primary">{{ __('assembly.meetings.agenda') }}</h2>
                    <p class="mt-3 leading-8 whitespace-pre-line text-ink">{{ $meeting->agenda }}</p>
                </x-card>
            @endif

            @if (! empty($meeting->attachments))
                <x-card class="mt-6">
                    <h2 class="text-lg font-bold text-primary">{{ __('assembly.meetings.attachments') }}</h2>
                    <ul class="mt-3 divide-y divide-line/70">
                        @foreach ($meeting->attachments as $index => $file)
                            <li>
                                <a href="{{ $meeting->attachmentUrl($index) }}" class="flex items-center gap-3 py-3 text-ink hover:text-primary">
                                    {{ svg('heroicon-o-document-arrow-down', 'size-6 shrink-0 text-accent') }}
                                    <span class="min-w-0 flex-1 break-words">{{ $file['name'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($meeting->members_count !== null)
                <x-card class="mt-6">
                    <h2 class="text-lg font-bold text-primary">{{ __('assembly.meetings.attendance') }}</h2>
                    <p class="mt-3 text-ink">{{ __('assembly.meetings.attendance_count', ['present' => number_format($attendees), 'total' => number_format($meeting->members_count)]) }}</p>
                    @if ($quorum !== null)
                        <p class="mt-2 font-semibold {{ $quorum ? 'text-primary' : 'text-accent-dark' }}">{{ __($quorum ? 'assembly.meetings.quorum_met' : 'assembly.meetings.quorum_not_met') }}</p>
                    @endif
                </x-card>
            @endif

            @if ($meeting->status === MeetingStatus::Held)
                <x-card class="mt-6">
                    <h2 class="text-lg font-bold text-primary">{{ __('assembly.meetings.minutes') }}</h2>
                    @if ($meeting->hasApprovedMinutes())
                        <x-button class="mt-3" variant="outline" :href="$meeting->minutesUrl()">{{ __('assembly.meetings.minutes_download') }}</x-button>
                    @else
                        <p class="mt-3 text-muted">{{ __('assembly.meetings.minutes_pending') }}</p>
                    @endif
                </x-card>
            @endif
        </div>
    </section>
</x-layouts.app>
