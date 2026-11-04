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

            <div class="mt-6 flex flex-col items-center justify-between gap-4 rounded-2xl border border-line/70 bg-white/80 p-5 text-center sm:flex-row sm:text-start">
                <p class="flex items-center gap-3 text-muted">
                    <x-icon-tile icon="storefront" size="sm" class="shrink-0" />
                    <span>{{ __('store.page.payment_note') }}</span>
                </p>
                @if ($storeUrl)
                    <x-button :href="$storeUrl" target="_blank" rel="noopener noreferrer" arrow class="shrink-0" data-store-enter>
                        {{ __('store.page.enter_store') }}<span class="sr-only"> {{ __('store.page.opens_in_new_tab') }}</span>
                    </x-button>
                @endif
            </div>

            {{ $grid }}
        </div>
    </section>
</x-layouts.app>
