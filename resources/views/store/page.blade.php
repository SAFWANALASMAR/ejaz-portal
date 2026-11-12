{{--
    «المتجر والتبرعات» (قرار صفوان، 4 نوفمبر 2026 — docs/decisions/2026-11-04-store-page.md): الرأس، ثم تبويبات التصنيف في
    الشريط العائم كـ«الدورات والبرامج» («الكل» دائماً، والتبرعات والإصدارات متى كان فيها عنصر منشور)، ثم سطر الدفع وزر «ادخل المتجر» برابط المتجر العام، ثم
    شبكة البطاقات الملونة (store/partials/grid). والدفع في متجر سلة بتبويب جديد، فلا تنقّل سريع على روابطه.
--}}
<x-layouts.app :title="__('store.page.title')">
    <x-page-hero :title="__('store.page.title')" />

    <section class="pb-12 sm:pb-16" data-store-page>
        <div class="mx-auto max-w-[82.5rem] px-4 sm:px-7">
            <nav aria-label="{{ __('store.page.tabs.label') }}" class="tabs-bar relative z-10 -mt-[2.125rem] mb-8 overflow-x-auto">
                <ul class="flex w-max gap-2">
                    <li>
                        <a href="{{ route('store.index') }}" wire:navigate @if (! $category) aria-current="page" @endif class="tab-pill">
                            {{ __('store.page.tabs.all') }}
                        </a>
                    </li>
                    @foreach ($tabs as $tab)
                        <li>
                            <a href="{{ route('store.index', ['category' => $tab->value]) }}" wire:navigate @if ($category === $tab) aria-current="page" @endif class="tab-pill">
                                {{ $tab->getLabel() }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="text-center">
                <x-missing :what="__('store.page.intro_missing')" />
            </div>

            {{-- سطر «الدفع يتم في متجر الجمعية…» محذوف بطلب صفوان (13 نوفمبر 2026)، ويبقى زر الدخول إلى المتجر وحده --}}
            @if ($storeUrl)
                <div class="mt-6 flex justify-center">
                    <x-button :href="$storeUrl" target="_blank" rel="noopener noreferrer" arrow class="shrink-0" data-store-enter>
                        {{ __('store.page.enter_store') }}<span class="sr-only"> {{ __('store.page.opens_in_new_tab') }}</span>
                    </x-button>
                </div>
            @endif

            {{ $grid }}
        </div>
    </section>
</x-layouts.app>
