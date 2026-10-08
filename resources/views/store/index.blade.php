{{--
    المتجر (نمط «برامجنا» — الصورة 06): رأس القسم، وزر إلى متجر الجمعية على سلة، ثم شبكة بطاقات
    المنتجات بالاسم والوصف وزر إلى المنتج. الشراء نفسه في سلة.
--}}
<x-layouts.app :title="__('store.title')" :description="$intro">
    <x-page-hero :title="__('store.title')" :summary="$intro" />

    <section class="py-10 sm:py-14">
        <div class="container-page">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-muted">{{ __('store.external') }}</p>
                @if ($storeUrl)
                    <x-button :href="$storeUrl" target="_blank" rel="noopener noreferrer" arrow>{{ __('store.shop') }}</x-button>
                @endif
            </div>

            @if ($products === [])
                <p class="mt-8 text-muted">{{ __('store.empty') }}</p>
            @else
                <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($products as $product)
                        <li class="flex h-full flex-col rounded-2xl border border-line/70 bg-white p-6 shadow-xs">
                            <span class="flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary">{{ svg('heroicon-o-shopping-bag', 'size-6') }}</span>
                            <h2 class="mt-4 text-lg font-bold text-ink">{{ $product->name }}</h2>
                            @if (filled($product->description))
                                <p class="mt-2 text-sm leading-6 text-muted">{{ $product->description }}</p>
                            @endif
                            @if ($product->safeUrl())
                                <div class="mt-auto pt-5">
                                    <x-button :href="$product->safeUrl()" target="_blank" rel="noopener noreferrer" variant="outline" class="w-full" arrow>{{ __('store.product_link') }}</x-button>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</x-layouts.app>
