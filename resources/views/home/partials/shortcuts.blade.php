{{--
    اختصارات الأقسام (الصورة 07): لوحة بيضاء بأعمدة بينها خطوط رفيعة. في كل عمود أيقونة،
    وعنوان، وسطر وصف، ودائرة ذهبية بسهم. بطاقة لكل قسم مبني فقط (BuildHomePage::SHORTCUTS).
--}}
@php
    $columns = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-2 lg:grid-cols-3', 4 => 'grid-cols-2 lg:grid-cols-4'][count($shortcuts)] ?? 'grid-cols-2 lg:grid-cols-4';
@endphp

<section class="bg-white py-12 lg:py-16" aria-labelledby="home-shortcuts">
    <div class="container-page">
        <h2 id="home-shortcuts" class="sr-only">{{ __('home.shortcuts.title') }}</h2>

        <ul class="grid gap-px overflow-hidden rounded-2xl border border-line/70 bg-line/70 shadow-sm {{ $columns }}">
            @foreach ($shortcuts as $shortcut)
                <li class="bg-white">
                    <a href="{{ $shortcut['url'] }}" class="group flex h-full flex-col items-center gap-2 px-4 py-8 text-center transition hover:bg-surface/60">
                        <span class="text-primary" aria-hidden="true">{{ svg('heroicon-o-'.$shortcut['icon'], 'size-8') }}</span>
                        <span class="mt-1 font-bold text-ink">{{ __('home.shortcuts.'.$shortcut['key'].'.title') }}</span>
                        <span class="text-sm leading-6 text-muted">{{ __('home.shortcuts.'.$shortcut['key'].'.text') }}</span>
                        {{-- السهم «للأمام» إلى اليسار في الموقع العربي، كما في x-button --}}
                        <span class="mt-3 inline-flex size-8 items-center justify-center rounded-full bg-accent text-white transition group-hover:bg-accent-dark" aria-hidden="true">
                            {{ svg('heroicon-o-arrow-left', 'size-4') }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</section>
