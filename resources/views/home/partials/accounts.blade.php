{{--
    قنوات التواصل (ملاحظات المدير التنفيذي، 25 أكتوبر 2026): حسابات الجمعية الرسمية من «إعدادات الموقع» بترتيب المنصات
    فيها، لكل حساب شعار منصته واسمها، في شريط داكن برمز الشهب. الروابط https آمنة (BuildHomePage::accounts) وتفتح خارجياً
    بلا صلة بالصفحة.
--}}
<section class="section-band py-14 lg:py-20" aria-labelledby="home-accounts">
    <span class="brand-emblem end-6 top-0 hidden h-full w-24 opacity-40 sm:block lg:end-16" aria-hidden="true"></span>

    <div class="container-page relative">
        <div class="mx-auto max-w-xl text-center">
            <span class="mx-auto block h-1 w-12 rounded-full bg-accent-light" aria-hidden="true"></span>
            <h2 id="home-accounts" class="mt-3 text-2xl font-bold sm:text-3xl">{{ $channels['title'] }}</h2>
            <p class="mt-2 text-white/80">
                {{ $channels['intro'] }}
                @if (\App\Support\HomeContent::isSample('channels', 'intro'))
                    <x-sample class="ms-1" />
                @endif
            </p>
        </div>

        <ul class="mt-10 flex flex-wrap justify-center gap-3 sm:gap-4">
            @foreach ($accounts as $account)
                <li class="w-[calc(50%-0.375rem)] sm:w-40">
                    <a href="{{ $account['url'] }}" target="_blank" rel="noopener noreferrer"
                        class="group flex h-full flex-col items-center gap-3 rounded-2xl border border-white/15 bg-white/5 px-4 py-5 text-center transition hover:-translate-y-0.5 hover:border-accent-light hover:bg-white/10">
                        <span class="inline-flex size-14 items-center justify-center rounded-full bg-white text-primary-dark shadow-md transition group-hover:scale-105">
                            <x-social-icon :network="$account['network']" class="size-7" />
                        </span>
                        <span class="font-bold">{{ __('settings.social.'.$account['network']) }}</span>
                        <span class="inline-flex items-center gap-1 text-xs text-white/75">
                            {{ __('home.accounts.visit') }}
                            {{ \App\Support\Icon::svg('arrow-up-left', 'size-3.5', ['aria-hidden' => 'true']) }}
                        </span>
                        <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</section>
