{{--
    عنصر في «قراءاتي» (Final-Dashboard «قراءاتي ومسموعاتي»): بطاقة ملونة (.tone-card) بمربع أيقونة قسمه، وعنوانه، وتقدّمه بشريط،
    وآخر نشاط، وزر المتابعة إلى صفحته في المكتبة من الموضع المحفوظ (?resume= — resources/js/library-progress.js).
    $progress تقدّم صاحب الحساب نفسه (BuildMyReading).
--}}
@php
    use App\Enums\LibrarySection;
    use App\Models\LibraryProgress;
    use App\Support\Dates;

    $item = $progress->item;
    $kind = $item->section->takesPdf() ? 'book' : ($item->section === LibrarySection::Audio ? 'audio' : 'video');
    $percent = $progress->percent();
    $resume = $kind === 'book' ? $progress->last_page : $progress->last_position;

    if ($kind === 'book' && $item->tracksPages() && $progress->last_page !== null) {
        $status = match (true) {
            // بصيغة العدد: «4 صفحات» لا «4 صفحة» (قبول ملاحظات 4 نوفمبر)، والإتمام بعدد صفحات الكتاب كله
            $progress->completed_at !== null => trans_choice('reading.status.completed', (int) $progress->total_pages, ['read' => number_format($progress->pages_count), 'total' => number_format((int) $progress->total_pages)]),
            $progress->total_pages !== null => trans_choice('reading.status.pages', $progress->pages_count, ['page' => number_format($progress->last_page), 'total' => number_format($progress->total_pages), 'read' => number_format($progress->pages_count)]),
            default => trans_choice('reading.status.pages_unknown_total', $progress->pages_count, ['page' => number_format($progress->last_page), 'read' => number_format($progress->pages_count)]),
        };
        $action = __('reading.actions.continue_reading');
    } elseif ($kind !== 'book' && $item->tracksPlayback() && $progress->last_position !== null) {
        $verb = $kind === 'audio' ? 'listened' : 'watched';
        $status = $progress->duration_seconds
            ? __('reading.status.'.$verb, ['position' => LibraryProgress::clock($progress->last_position), 'duration' => LibraryProgress::clock($progress->duration_seconds)])
            : __('reading.status.'.$verb.'_no_duration', ['position' => LibraryProgress::clock($progress->last_position)]);
        // ومن بلغ النهاية لا «أكمل» له (قبول ملاحظات 4 نوفمبر: كان رابطه يبدأ من النهاية)، بل رابط من البداية بلا resume
        if ($progress->playedToEnd()) {
            $action = __($kind === 'audio' ? 'reading.actions.listen_again' : 'reading.actions.watch_again');
            $resume = null;
        } else {
            $action = __($kind === 'audio' ? 'reading.actions.continue_listening' : 'reading.actions.continue_watching');
        }
    } else {
        $status = __('reading.status.opened_'.$kind);
        $action = __('reading.actions.open');
        $resume = null;
        $percent = null;
    }

    $href = route('library.show', [$item->section->value, $item->slug, ...($resume ? ['resume' => $resume] : [])]);
@endphp

<li class="tone-card flex flex-wrap items-center gap-4 p-4 sm:flex-nowrap sm:p-5" data-reading-item data-reading-kind="{{ $kind }}">
    <x-icon-tile :icon="$item->section->icon()" size="md" />
    <span class="flex min-w-0 flex-1 basis-48 flex-col gap-1.5">
        <span class="text-[1.0625rem] font-extrabold break-words">{{ $item->title }}</span>
        <span class="tone-card-muted text-sm">{{ $status }}</span>
        @if ($percent !== null)
            <progress max="100" value="{{ $percent }}" aria-label="{{ __('reading.progress', ['title' => $item->title]) }}"
                class="block h-2 w-full appearance-none overflow-hidden rounded-full bg-white/30 forced-colors:appearance-auto [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-current [&::-webkit-progress-bar]:bg-white/30 [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-current">{{ $percent }}%</progress>
        @endif
        <span class="tone-card-muted text-xs">{{ __('reading.status.last_activity', ['date' => Dates::display($progress->last_reported_at)]) }}</span>
    </span>
    <a href="{{ $href }}" wire:navigate class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-current/40 bg-white/15 px-4 py-2 text-sm font-extrabold transition hover:bg-white/25" data-reading-action>
        {{ $action }}<span class="sr-only">: {{ $item->title }}</span>
        {{ \App\Support\Icon::svg('arrow-left', 'size-4') }}
    </a>
</li>
