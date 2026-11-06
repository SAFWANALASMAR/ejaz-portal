{{--
    شبكة بطاقات «المتجر والتبرعات» (.tile-grid: الصف الأخير الناقص في الوسط): بطاقة ملونة تتعاقب درجتها (tone-card) كبطاقة البرنامج: الصورة، ثم التصنيف، ثم العنوان،
    ثم الوصف القصير، ثم زر «تبرّع» أو «اشترِ» بعرض كامل إلى رابط الشراء في سلة (StoreCatalog::purchaseUrl) بتبويب جديد. وتُخزَّن
    نصاً (ContentCache)، فلا شيء فيها من الطلب. وبلا عناصر حالة فارغة مرسومة. وبطاقات أصغر (ملاحظات المدير ليلة 5 نوفمبر 2026، البند 2:
    «الأيقونات كبيرة جداً»): أربع في الصف على الحاسوب، وصورة أقصر (16:9)، وعنوان وزر أصغر.
--}}
@if ($cards === [])
    <x-empty-state icon="storefront" :message="__('store.page.empty')" class="mt-10" />
@else
    <ul class="tile-grid tile-grid-4 mt-8" data-reveal-group data-store-grid>
        @foreach ($cards as $card)
            @php($product = $card['product'])
            <li>
                <article class="tone-card group flex h-full flex-col overflow-hidden" data-store-item="{{ $card['category']->value }}">
                    <div class="tone-card-media aspect-[16/9]">
                        <img src="{{ $product->image ?? \App\Support\NeutralImage::forSeed(crc32($product->name)) }}" alt="" width="640" height="360"
                            class="size-full object-cover transition duration-700 group-hover:scale-105" loading="lazy">
                    </div>

                    <div class="flex flex-1 flex-col gap-1.5 px-4 pt-3.5 pb-4">
                        <span class="tone-chip self-start">{{ $card['category']->getLabel() }}</span>
                        <h3 class="line-clamp-2 text-lg leading-snug font-black">{{ $product->name }}</h3>
                        @if (filled($product->description))
                            <p class="tone-card-muted line-clamp-2 text-sm leading-6">{{ $product->description }}</p>
                        @endif

                        @if ($card['href'])
                            <a href="{{ $card['href'] }}" target="_blank" rel="noopener noreferrer" class="tone-pill mt-auto w-full py-2 text-base" data-store-buy>
                                {{ $card['category']->action() }}<span class="sr-only">: {{ $product->name }} {{ __('store.page.opens_in_new_tab') }}</span>
                            </a>
                        @endif
                    </div>
                </article>
            </li>
        @endforeach
    </ul>
@endif
