{{--
    حساباتنا الرسمية (ملاحظة المدير التنفيذي): من حسابات التواصل في إعدادات الموقع، في شريط داكن
    برمز الشهب. الروابط https آمنة (BuildHomePage::accounts) وتفتح خارجياً بلا صلة بالصفحة.
--}}
<section class="section-band py-12 lg:py-16" aria-labelledby="home-accounts">
    <span class="brand-emblem end-6 top-0 hidden h-full w-24 opacity-50 sm:block lg:end-16" aria-hidden="true"></span>

    <div class="container-page relative">
        <span class="block h-1 w-12 rounded-full bg-accent-light" aria-hidden="true"></span>
        <h2 id="home-accounts" class="mt-3 text-2xl font-bold sm:text-3xl">{{ __('home.accounts.title') }}</h2>

        <ul class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($accounts as $account)
                <li>
                    <a href="{{ $account['url'] }}" target="_blank" rel="noopener noreferrer"
                        class="flex h-full items-center justify-between gap-3 rounded-2xl border border-white/15 bg-white/5 px-5 py-4 transition hover:border-accent-light hover:bg-white/10">
                        <span class="font-bold">{{ __('settings.social.'.$account['network']) }}</span>
                        <span class="inline-flex items-center gap-1 text-sm text-white/75">
                            {{ __('home.accounts.visit') }}
                            {{ svg('heroicon-o-arrow-up-left', 'size-4', ['aria-hidden' => 'true']) }}
                        </span>
                        <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</section>
