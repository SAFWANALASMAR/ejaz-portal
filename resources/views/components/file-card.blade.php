{{--
    بطاقة ملف حديثة: أيقونة بنوعه، والعنوان، وسطر البيانات، والحجم، وزرا العرض والتنزيل.
    $href صفحة الملف أو معاينته، و$download رابط التنزيل (اختياري)، و$bytes حجمه.
--}}
@props(['title', 'href', 'download' => null, 'bytes' => null, 'type' => 'PDF', 'meta' => []])

@php
    $size = \App\Support\FileSize::display($bytes);
    $details = array_values(array_filter([...$meta, $size]));
@endphp

<article {{ $attributes->class('file-card') }}>
    <span class="flex size-14 shrink-0 flex-col items-center justify-center rounded-xl bg-accent text-white" aria-hidden="true">
        {{ svg('heroicon-o-document-text', 'size-6') }}
        <span class="text-[10px] leading-none font-bold tracking-wide">{{ $type }}</span>
    </span>

    <div class="min-w-0 flex-1">
        <h3 class="font-bold text-ink">
            <a href="{{ $href }}" class="hover:text-primary hover:underline">{{ $title }}</a>
        </h3>
        @if ($details !== [])
            <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-muted">
                @foreach ($details as $detail)
                    <span>{{ $detail }}</span>
                @endforeach
            </p>
        @endif
    </div>

    <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
        <a href="{{ $href }}" class="inline-flex items-center gap-1 rounded-full border border-primary px-3 py-1.5 text-sm font-bold text-primary hover:bg-primary-soft">
            {{ svg('heroicon-o-eye', 'size-4') }}<span>{{ __('site.file.preview') }}</span>
        </a>
        @if ($download)
            <a href="{{ $download }}" class="inline-flex items-center gap-1 rounded-full bg-primary px-3 py-1.5 text-sm font-bold text-white hover:bg-primary-dark">
                {{ svg('heroicon-o-arrow-down-tray', 'size-4') }}<span>{{ __('site.file.download') }}</span>
            </a>
        @endif
    </div>
</article>
