{{--
    «خدماتنا — كل ما تحتاجه في بوابة واحدة» (مرجع المدير التنفيذي، والتصور المعتمد 11:58): ست بطاقات بيضاء ترتفع بحد متوهج
    وتميل قليلاً مع الفأرة على الحاسوب، في كل بطاقة مربع أيقونة متدرج بلون من الهوية (x-icon-tile) تتحرك أيقونته، ثم الاسم
    والوصف وزر «ادخل». وبجانبها بطاقة صورة بلمعة إلى «عن الجمعية»، نصاها من اللوحة («محتوى الرئيسية» — identity) فلا تظهر
    بلا نص. بطاقة لكل قسم مبني فقط (BuildHomePage::SHORTCUTS).
--}}
@php
    $sideTitle = trim((string) ($content['identity']['side_title'] ?? ''));
    $sideText = trim((string) ($content['identity']['side_text'] ?? ''));
    $side = $sideTitle !== '' && Route::has('about');
@endphp

<section class="mesh-light relative overflow-hidden py-16 lg:py-24" aria-labelledby="home-shortcuts">
    <div class="bg-pattern pointer-events-none absolute inset-0 opacity-40" aria-hidden="true"></div>

    <div class="container-page relative">
        <x-section-heading id="home-shortcuts" :eyebrow="__('ui.services.eyebrow')" center>{{ __('ui.services.title') }}</x-section-heading>

        <div @class(['mt-12 grid gap-5', 'lg:grid-cols-[1fr_20rem]' => $side])>
            <ul class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3" data-reveal-group>
                @foreach ($shortcuts as $shortcut)
                    <li>
                        <a href="{{ $shortcut['url'] }}" @navigate($shortcut['url']) class="card-glow tilt group flex h-full flex-col gap-4 p-6">
                            <x-icon-tile :icon="$shortcut['icon']" :tone="$shortcut['tone']" size="lg" />
                            <span class="text-xl font-bold text-primary-dark">{{ __('home.shortcuts.'.$shortcut['key'].'.title') }}</span>
                            <span class="leading-7 text-muted">{{ __('home.shortcuts.'.$shortcut['key'].'.text') }}</span>
                            <span class="mt-auto inline-flex items-center gap-2 self-start rounded-full bg-primary-dark px-4 py-1.5 text-sm font-semibold text-white transition group-hover:bg-primary">
                                {{ __('ui.services.enter') }}
                                {{ \App\Support\Icon::svg('arrow-left', 'size-4 transition group-hover:-translate-x-1', ['aria-hidden' => 'true']) }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($side)
                <a href="{{ route('about') }}" wire:navigate data-side-card class="shine group relative flex min-h-80 flex-col justify-end overflow-hidden rounded-3xl bg-primary-dark p-7 text-white" data-reveal>
                    <img src="{{ $content['rationale']['image'] }}" alt="" loading="lazy" width="768" height="512"
                        class="absolute inset-0 -z-0 size-full object-cover opacity-60 transition duration-700 group-hover:scale-105">
                    <span class="absolute inset-0 bg-linear-to-t from-primary-deep via-primary-dark/70 to-transparent" aria-hidden="true"></span>
                    <span class="relative text-2xl leading-snug font-bold">{{ $sideTitle }}</span>
                    @if ($sideText !== '')
                        <span class="relative mt-2 text-white/85">{{ $sideText }}</span>
                    @endif
                </a>
            @endif
        </div>
    </div>
</section>
