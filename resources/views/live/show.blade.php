{{--
    صفحة البث المباشر (نمط رأس الأقسام 05 ثم بطاقة بيضاء): الجاري بمشغّل يوتيوب مدمجاً من
    youtube-nocookie.com أو بزر الانضمام عبر تيمز، والقادم بموعده بتوقيت الرياض وعدّاد تنازلي
    (resources/js/live-countdown.js) يعيد تحميل الصفحة عند الصفر فيظهر المشغّل.
--}}
@php
    use App\Enums\LiveStreamStatus;
    use App\Support\Dates;
@endphp

<x-layouts.app :title="__('livestream.title')" :description="$stream->title">
    <x-page-hero :title="$stream->title" :summary="__('livestream.show.schedule', ['date' => Dates::display($stream->starts_at, withTime: true)])" />

    <section class="py-10 sm:py-14">
        <div class="container-page">
            @if ($status === LiveStreamStatus::Live)
                @if ($stream->youtubeId())
                    <div class="overflow-hidden rounded-2xl border border-line/70 bg-ink shadow-xs">
                        <iframe src="https://www.youtube-nocookie.com/embed/{{ $stream->youtubeId() }}" title="{{ __('livestream.show.player', ['title' => $stream->title]) }}"
                            allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"
                            class="block aspect-video w-full"></iframe>
                    </div>
                @elseif ($stream->joinUrl())
                    <div class="rounded-2xl border border-line/70 bg-white p-8 text-center shadow-xs sm:p-12">
                        <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-sm font-semibold text-red-700">
                            <span class="size-2 animate-pulse rounded-full bg-red-600" aria-hidden="true"></span>
                            {{ __('livestream.statuses.live') }}
                        </span>
                        <p class="mx-auto mt-4 max-w-xl leading-7 text-muted">{{ __('livestream.show.teams_intro') }}</p>
                        <x-button :href="$stream->joinUrl()" target="_blank" rel="noopener noreferrer" size="lg" class="mt-6" arrow>{{ __('livestream.show.join_teams') }}</x-button>
                    </div>
                @endif
            @else
                <div class="rounded-2xl border border-line/70 bg-white p-8 text-center shadow-xs sm:p-12">
                    <p class="text-sm font-semibold text-accent-dark">{{ __('livestream.show.starts_at') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary sm:text-3xl">{{ Dates::display($stream->starts_at, withTime: true) }}</p>
                    <p data-countdown="{{ $stream->starts_at->toIso8601String() }}" data-countdown-labels="{{ json_encode($labels, JSON_UNESCAPED_UNICODE) }}"
                        class="mt-6 text-3xl font-bold tabular-nums text-ink sm:text-4xl" aria-live="polite"></p>
                    <p class="mt-3 text-sm text-muted">{{ __('livestream.show.countdown_hint') }}</p>
                </div>
            @endif

            @if (filled($stream->description))
                <div class="mt-8">
                    <x-section-heading>{{ __('livestream.show.about') }}</x-section-heading>
                    <p class="mt-4 leading-8 whitespace-pre-line text-ink">{{ $stream->description }}</p>
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
