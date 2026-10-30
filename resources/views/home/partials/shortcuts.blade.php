{{--
    «خدماتنا — كل ما تحتاجه في بوابة واحدة» (التصور النهائي — Final-Home): ست بطاقات بيضاء ترتفع عند المرور بحد سماوي، في كل
    بطاقة الاسم وأيقونته الخطية في سطر (تدور الأيقونة قليلاً مع المرور)، ثم الوصف، ثم «ادخل» كبسولة داكنة في الوسط. وبجانبها
    بطاقة صورة إلى «عن الجمعية» نصاها من اللوحة («محتوى الرئيسية» — identity)، فلا تظهر بلا عنوان. بطاقة لكل قسم مبني فقط
    (BuildHomePage::SHORTCUTS).
--}}
@php
    $sideTitle = trim((string) ($content['identity']['side_title'] ?? ''));
    $sideText = trim((string) ($content['identity']['side_text'] ?? ''));
    $side = $sideTitle !== '' && Route::has('about');
@endphp

<section class="band-sky" aria-labelledby="home-shortcuts">
    <div class="mx-auto max-w-[82.5rem] px-4 pt-11 pb-13 sm:px-7">
        <x-section-heading id="home-shortcuts" center :description="__('ui.services.title')" class="mb-6.5">{{ __('ui.services.eyebrow') }}</x-section-heading>

        <div @class(['grid gap-5', 'lg:grid-cols-[3fr_1fr]' => $side])>
            <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-reveal-group>
                @foreach ($shortcuts as $shortcut)
                    <li>
                        <a href="{{ $shortcut['url'] }}" @navigate($shortcut['url']) class="card-lift group flex h-full flex-col gap-2 px-5.5 pt-5.5 pb-4.5">
                            <span class="flex items-center justify-between gap-3">
                                <span class="text-[1.3125rem] font-black text-primary-dark">{{ __('home.shortcuts.'.$shortcut['key'].'.title') }}</span>
                                <x-icon-tile :icon="$shortcut['icon']" :index="$loop->index" />
                            </span>
                            <span class="min-h-[3.375rem] text-base leading-[1.7] text-muted">{{ __('home.shortcuts.'.$shortcut['key'].'.text') }}</span>
                            <span class="mt-auto self-center rounded-full bg-primary-dark px-5.5 py-1 text-sm font-extrabold text-white transition group-hover:bg-brand">{{ __('ui.services.enter') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($side)
                <a href="{{ route('about') }}" wire:navigate data-side-card class="group relative flex min-h-[22.5rem] flex-col justify-end overflow-hidden rounded-[1.25rem] p-6 text-white" data-reveal>
                    <img src="{{ $content['rationale']['image'] }}" alt="" loading="lazy" width="768" height="512"
                        class="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-105">
                    <span class="absolute inset-0 bg-linear-to-b from-primary-dark/15 from-20% to-primary-deep/90 to-80%" aria-hidden="true"></span>
                    <span class="relative text-[1.75rem] leading-[1.3] font-black">{{ $sideTitle }}</span>
                    @if ($sideText !== '')
                        <span class="relative text-base text-[var(--concept-pale)]">{{ $sideText }}</span>
                    @endif
                </a>
            @endif
        </div>
    </div>
</section>
