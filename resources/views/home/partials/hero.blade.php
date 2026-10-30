{{--
    البطل الحي (مرجع المدير التنفيذي 17:42 والتصور المعتمد 11:58 — plans/2026-10-30-director-home-reference.md): عارض صور
    الفعاليات (x-hero-slider) والنص في جهة البداية: شارة الإشراف بأيقونة، والعنوان سطران يظهران كلمة كلمة والثاني بالأزرق
    الفاتح بخط منحني يُرسم تحته، والتعريف، وزر رئيسي بتوهج خفيف وزر ثانوي بإطار. والنصوص من «إعدادات الموقع» و«محتوى
    الرئيسية» وملف الترجمة (اسم الجمعية واختصاصها)، فلا نص من صورة المرجع.
--}}
@php
    $title = $hero['title'];
    $short = __('site.short_name');
    // «جمعية إعجاز» سطراً أول، والبقية («للدراسات والبحوث العلمية») سطراً مميزاً، متى كان العنوان اسم الجمعية
    $rest = str_starts_with($title, $short) ? trim(mb_substr($title, mb_strlen($short))) : '';
    $primary = $hero['button'] ?? ($prize !== null ? ['label' => __('ui.prize.cta'), 'url' => $prize['url']] : null);
@endphp

<x-hero-slider :slides="$slides">
    <div class="max-w-3xl">
        <p data-hero-badge class="glass inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm text-white" data-reveal>
            {{ \App\Support\Icon::svg('academic-cap', 'size-4 shrink-0 text-brand-light', ['aria-hidden' => 'true']) }}
            {{ \App\Support\HomeContent::text('footer', 'supervision') }}
        </p>

        <h1 data-words class="mt-6 text-[2.6rem] leading-[1.15] font-black text-balance text-white sm:text-6xl xl:text-7xl">
            @if ($rest !== '')
                {{ $short }}<br>
                <span data-highlight class="hero-highlight">{{ $rest }}<svg data-swoosh class="hero-swoosh" viewBox="0 0 420 24" preserveAspectRatio="none" fill="none" aria-hidden="true"><path d="M4 16 C 110 2, 300 2, 416 14" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg></span>
            @else
                <span data-highlight class="hero-highlight">{{ $title }}<svg data-swoosh class="hero-swoosh" viewBox="0 0 420 24" preserveAspectRatio="none" fill="none" aria-hidden="true"><path d="M4 16 C 110 2, 300 2, 416 14" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg></span>
            @endif
        </h1>

        <p class="mt-10 max-w-xl text-lg leading-9 text-white/90 sm:text-xl">{{ $hero['text'] }}</p>

        <div class="mt-9 flex flex-wrap gap-3">
            @if ($primary)
                <a href="{{ $primary['url'] }}" @navigate($primary['url'])
                    class="btn-glow btn-shine inline-flex items-center gap-2 rounded-full bg-brand-light px-7 py-3.5 text-lg font-bold text-primary-deep transition hover:bg-white" data-loop>
                    {{ $primary['label'] }}
                    {{ \App\Support\Icon::svg('arrow-left', 'size-5', ['aria-hidden' => 'true']) }}
                </a>
            @endif
            @if (Route::has('library.index'))
                <a href="{{ route('library.index') }}" wire:navigate
                    class="inline-flex items-center gap-2 rounded-full px-7 py-3.5 text-lg font-semibold text-white ring-1 ring-white/40 transition hover:bg-white/10">
                    {{ __('ui.hero.library') }}
                </a>
            @endif
        </div>
    </div>
</x-hero-slider>
