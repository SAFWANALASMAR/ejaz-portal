{{--
    اختصارات الأقسام (الصورة 07، والمرجع الأسلوبي — docs/plans/visual-refresh.md): بطاقات بيضاء بظل ناعم ترتفع وتميل
    قليلاً عند المرور، في كل بطاقة أيقونة في مربع بتدرج خفيف، وعنوان، وسطر وصف، ودائرة بنفسجية بسهم. بطاقة لكل قسم مبني
    فقط (BuildHomePage::SHORTCUTS).
--}}
@php
    $columns = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-2 lg:grid-cols-3', 4 => 'grid-cols-2 lg:grid-cols-4', 5 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5', 6 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6'][count($shortcuts)] ?? 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6';
@endphp

<section class="bg-white py-12 lg:py-16" aria-labelledby="home-shortcuts">
    <div class="container-page">
        <h2 id="home-shortcuts" class="sr-only">{{ __('home.shortcuts.title') }}</h2>

        <ul class="grid gap-4 sm:gap-5 {{ $columns }}" data-reveal-group>
            @foreach ($shortcuts as $shortcut)
                {{-- عدد فردي من البطاقات في عمودين: الأخيرة بعرض الصف فلا تبقى يتيمة --}}
                <li @class(['col-span-2 sm:col-span-1' => $loop->last && count($shortcuts) % 2 === 1])>
                    <a href="{{ $shortcut['url'] }}" @navigate($shortcut['external'] ? null : $shortcut['url']) @if ($shortcut['external']) target="_blank" rel="noopener noreferrer" @endif class="card-lift tilt group flex h-full flex-col items-center gap-2 px-4 py-8 text-center">
                        <span class="icon-tile size-16" aria-hidden="true">{{ \App\Support\Icon::svg($shortcut['icon'], 'size-8') }}</span>
                        <span class="mt-2 font-bold text-ink group-hover:text-primary">{{ __('home.shortcuts.'.$shortcut['key'].'.title') }}</span>
                        <span class="mb-3 text-sm leading-6 text-muted">{{ __('home.shortcuts.'.$shortcut['key'].'.text') }}</span>
                        @if ($shortcut['external'])
                            <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                        @endif
                        {{-- السهم «للأمام» إلى اليسار في الموقع العربي، كما في x-button. وفي أسفل البطاقة (mt-auto)،
                             فتتحاذى الأسهم في الصف الواحد مهما طال الوصف --}}
                        <span class="mt-auto inline-flex size-8 items-center justify-center rounded-full bg-accent text-white transition group-hover:bg-accent-dark" aria-hidden="true">
                            {{ \App\Support\Icon::svg('arrow-left', 'size-4') }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</section>
