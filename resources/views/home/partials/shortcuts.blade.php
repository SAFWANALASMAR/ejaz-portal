{{--
    «خدماتنا — كل ما تحتاجه في بوابة واحدة» (التصور النهائي — Final-Home): ست بطاقات بيضاء ترتفع عند المرور بحد سماوي، في كل
    بطاقة الاسم وأيقونته الخطية في سطر (تدور الأيقونة قليلاً مع المرور)، ثم الوصف، ثم «ادخل» كبسولة داكنة في الوسط. وبجانبها
    بطاقة صورة إلى «عن الجمعية» نصاها من اللوحة («محتوى الرئيسية» — identity)، فلا تظهر بلا عنوان. بطاقة لكل قسم مبني فقط
    (BuildHomePage::SHORTCUTS).
    وملاحظات المدير التنفيذي (31 أكتوبر 01:28): كل بطاقة بتدرج فاتح من لون مربع أيقونتها (.service-card)، والعنوان والسطر تحته بلا
    فراغ، والمقطع أوسع وأطول.
--}}
@php
    $sideTitle = trim((string) ($content['identity']['side_title'] ?? ''));
    $sideText = trim((string) ($content['identity']['side_text'] ?? ''));
    $side = $sideTitle !== '' && Route::has('about');
@endphp

<section class="home-band" data-band="2" aria-labelledby="home-shortcuts">
    <div class="mx-auto max-w-[88rem] px-4 pt-14 pb-16 sm:px-7">
        <x-section-heading id="home-shortcuts" center :description="__('ui.services.title')" class="mb-8 gap-0!">{{ __('ui.services.eyebrow') }}</x-section-heading>

        <div @class(['grid gap-5', 'lg:grid-cols-[3fr_1fr]' => $side])>
            <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-reveal-group>
                @foreach ($shortcuts as $shortcut)
                    <li>
                        {{-- على الجوال (Final-Mobile) بطاقة أفقية: الأيقونة، ثم الاسم والوصف، ثم «ادخل» في جهة النهاية --}}
                        <a href="{{ $shortcut['url'] }}" @navigate($shortcut['url'])
                            data-tone="{{ \App\Support\IconTones::at($loop->index) }}" data-service-card
                            class="card-lift service-card group grid h-full grid-cols-[auto_1fr_auto] items-center gap-x-3 gap-y-0.5 px-4 py-4 sm:flex sm:flex-col sm:items-stretch sm:gap-2.5 sm:px-6 sm:pt-6.5 sm:pb-5.5">
                            <span class="contents sm:flex sm:items-center sm:justify-between sm:gap-3">
                                <span class="col-start-2 row-start-1 text-lg font-black text-primary-dark sm:text-[1.3125rem]">{{ __('home.shortcuts.'.$shortcut['key'].'.title') }}</span>
                                <x-icon-tile :icon="$shortcut['icon']" :index="$loop->index" size="lg" class="col-start-1 row-span-2 row-start-1" />
                            </span>
                            <span class="col-start-2 row-start-2 text-sm leading-6 text-muted sm:min-h-[3.375rem] sm:text-base sm:leading-[1.7]">{{ __('home.shortcuts.'.$shortcut['key'].'.text') }}</span>
                            <span class="col-start-3 row-span-2 row-start-1 rounded-full bg-primary-dark px-4 py-1 text-sm font-extrabold text-white transition group-hover:bg-primary sm:mt-auto sm:self-center sm:px-5.5">{{ __('ui.services.enter') }}</span>
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
