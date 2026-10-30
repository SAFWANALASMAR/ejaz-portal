{{--
    بطاقة ملف (التصور النهائي — Final-Governance): مربع أيقونة متدرج بنوع الملف ولونه من موضعه (index)، والعنوان، وسطر
    البيانات والحجم، وزر «عرض» داكن ممتلئ و«تنزيل» بإطار. ترتفع وتلمع عند المرور (card-lift).
    $href صفحة الملف أو معاينته، و$download رابط التنزيل (اختياري)، و$bytes حجمه.
    الرابطان من route() أو رابط مؤقت موقّع بعد فحص الصلاحية، لا رابط يكتبه مستخدم.
--}}
@props(['title', 'href', 'type', 'download' => null, 'bytes' => null, 'meta' => [], 'index' => 0])

@php
    $size = \App\Support\FileSize::display($bytes);
    $details = array_values(array_filter([...$meta, $size]));
@endphp

<article data-file-card {{ $attributes->class('card-lift group flex items-center gap-4 rounded-[1.125rem] px-4 py-3.5') }}>
    <span class="relative shrink-0">
        <x-icon-tile icon="document-text" :index="$index" size="md" />
        <span class="absolute inset-x-0 bottom-1.5 text-center text-[9px] leading-none font-bold tracking-wide text-white" aria-hidden="true">{{ $type }}</span>
    </span>

    <div class="min-w-0 flex-1">
        <h3 class="text-lg font-black text-primary-dark">
            <a href="{{ $href }}" @navigate($href) class="hover:text-primary hover:underline">{{ $title }}</a>
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
        <a href="{{ $href }}" @navigate($href) class="inline-flex items-center gap-1 rounded-full bg-primary-dark px-4 py-1.5 text-sm font-bold text-white transition hover:bg-primary">
            {{ \App\Support\Icon::svg('eye', 'size-4') }}<span>{{ __('site.file.preview') }}<span class="sr-only">: {{ $title }}</span></span>
        </a>
        @if ($download)
            <a href="{{ $download }}" class="inline-flex items-center gap-1 rounded-full border border-[var(--concept-border)] px-4 py-1.5 text-sm font-bold text-primary-dark transition hover:border-[var(--concept-border-hover)]">
                {{ \App\Support\Icon::svg('arrow-down-tray', 'size-4') }}<span>{{ __('site.file.download') }}<span class="sr-only">: {{ $title }}</span></span>
            </a>
        @endif
    </div>
</article>
