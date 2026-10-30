{{--
    التذييل الداكن (التصور النهائي — docs/design/final-concept): أربعة أعمدة: «شركاؤنا» بشعاراتهم متى نُشروا، و«تواصل معنا»،
    و«روابط سريعة»، والشعار والمخطوطة بالأبيض وسطر الإشراف والحسابات بأيقونات دائرية. ثم سطر سفلي: سياسة الخصوصية وخريطة
    الموقع، والحقوق، وزر العودة للأعلى. نصوصه من «محتوى الرئيسية» (HomeContent، القسم footer)، وبيانات التواصل والقنوات من
    «إعدادات الموقع»، والشركاء من اللوحة (SiteChromeComposer)، وما لم يُدخل لا يظهر. ولا رقم ولا بريد ولا شعار من التصور.
--}}
@php
    $menu = $siteMenu ?? collect();
    $contact = $siteContact ?? [];
    $text = fn (string $key): string => \App\Support\HomeContent::text('footer', $key);
    // فحص عند العرض أيضاً، فلا يُعرض رابط غير آمن ولو وصل إلى القاعدة دون تحقق
    $social = array_filter($contact['social'] ?? [], fn ($url) => is_string($url) && \App\Rules\SafeUrl::isSafe($url, allowInternal: false));
    $email = is_string($contact['email'] ?? null) && $contact['email'] !== '' ? $contact['email'] : null;
    $phone = is_string($contact['phone'] ?? null) && $contact['phone'] !== '' ? $contact['phone'] : null;
    $address = is_string($contact['address'] ?? null) && $contact['address'] !== '' ? $contact['address'] : null;
    $shortAddress = \App\Support\NationalAddress::normalize(is_string($contact['short_address'] ?? null) ? $contact['short_address'] : null);
    $map = is_string($contact['map_url'] ?? null) && \App\Rules\SafeUrl::isSafe($contact['map_url'], allowInternal: false)
        ? $contact['map_url']
        : \App\Support\NationalAddress::mapUrl($shortAddress);
    $whatsapp = \App\Support\WhatsApp::link(is_string($contact['whatsapp'] ?? null) ? $contact['whatsapp'] : null);
    $hasContact = $email !== null || $phone !== null || $address !== null || $shortAddress !== null || $whatsapp !== null;
    // شعارات الشركاء المنشورة بترتيبها في اللوحة (SiteChromeComposer، مخزّنة)، والبقية في صفحة الشركاء
    $partners = $footerPartners ?? [];
@endphp

<footer class="bg-primary-dark text-white/85" data-site-footer>
    <div class="mx-auto grid max-w-[82.5rem] gap-8 px-4 pt-11 pb-7 sm:grid-cols-2 sm:px-7 lg:grid-cols-[1.2fr_1fr_1fr_1.3fr]">
        {{-- شركاؤنا: لا يظهر العمود قبل نشر شريك بشعاره --}}
        <div data-footer-column class="flex flex-col gap-3">
            @if ($partners !== [])
                <h2 class="text-[19px] font-black text-white">{{ __('ui.footer.partners') }}</h2>
                <ul class="grid grid-cols-2 gap-3">
                    @foreach ($partners as $partner)
                        <li class="flex h-[4.75rem] items-center justify-center rounded-[0.875rem] bg-white p-3" title="{{ $partner['name'] }}">
                            <img src="{{ Storage::disk('public')->url($partner['logo']) }}" alt="{{ $partner['name'] }}" loading="lazy" width="160" height="80" class="max-h-full w-auto object-contain">
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- تواصل معنا --}}
        <div data-footer-column class="flex flex-col gap-3">
            <h2 class="text-[19px] font-black text-white">{{ $text('contact') }}</h2>
            @if ($hasContact)
                <ul class="space-y-3">
                    @if ($email !== null)
                        <li class="flex items-center gap-2">
                            {{ \App\Support\Icon::svg('envelope', 'size-[18px] shrink-0 text-brand-light', ['aria-hidden' => 'true']) }}
                            <a href="mailto:{{ $email }}" class="hover:text-white" dir="ltr">{{ $email }}</a>
                        </li>
                    @endif
                    @if ($phone !== null)
                        <li class="flex items-center gap-2">
                            {{ \App\Support\Icon::svg('phone', 'size-[18px] shrink-0 text-brand-light', ['aria-hidden' => 'true']) }}
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="hover:text-white"><bdi dir="ltr">{{ $phone }}</bdi></a>
                        </li>
                    @endif
                    @if ($whatsapp !== null)
                        <li>
                            <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" data-whatsapp
                                class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-1.5 font-semibold text-primary-dark transition hover:bg-accent-light">
                                <x-social-icon network="whatsapp" class="size-4" />
                                {{ __('contact.info.whatsapp') }}
                                <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                            </a>
                        </li>
                    @endif
                    @if ($address !== null || $shortAddress !== null)
                        <li class="flex items-start gap-2">
                            {{ \App\Support\Icon::svg('map-pin', 'mt-1 size-[18px] shrink-0 text-brand-light', ['aria-hidden' => 'true']) }}
                            <span class="space-y-1 leading-7">
                                @if ($address !== null)
                                    <span class="block">{{ $address }}</span>
                                @endif
                                @if ($shortAddress !== null)
                                    <span class="block text-white/75">{{ __('settings.contact.short_address') }}: <bdi dir="ltr">{{ $shortAddress }}</bdi></span>
                                @endif
                                @if ($map !== null)
                                    <a href="{{ $map }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-semibold text-brand-light hover:text-white">
                                        {{ __('contact.info.map') }}
                                        {{ \App\Support\Icon::svg('arrow-up-left', 'size-3.5', ['aria-hidden' => 'true']) }}
                                        <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                                    </a>
                                @endif
                            </span>
                        </li>
                    @endif
                </ul>
            @endif
        </div>

        {{-- روابط سريعة --}}
        <nav data-footer-column aria-labelledby="footer-links" class="flex flex-col gap-2.5">
            <h2 id="footer-links" class="text-[19px] font-black text-white">{{ $text('quick_links') }}</h2>
            <ul class="flex flex-col gap-2 text-[15px]">
                {{-- العنصر الذي يشير إلى صفحة غير منشورة يبقى في الرأس لعناصره الفرعية، ولا رابط له هنا (SiteChromeComposer) --}}
                @foreach ($menu->where('linkable', true) as $item)
                    <li>
                        <a href="{{ $item->href() }}" @if ($item->opensInNewTab) target="_blank" rel="noopener" @endif @if ($item->navigates()) wire:navigate @endif
                            class="text-white/80 transition hover:text-white">{{ $item->label }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- الشعار والإشراف والحسابات --}}
        <div data-footer-column class="flex flex-col items-start gap-3">
            <x-brand light size="footer" />
            <p class="text-[13px]">{{ $text('supervision') }}</p>
            <p class="text-xs text-white/75">{{ $text('license') }}</p>
            @if ($social !== [])
                <ul class="flex flex-wrap gap-2" aria-label="{{ $text('channels') }}">
                    @foreach ($social as $network => $url)
                        <li>
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ __('settings.social.'.$network) }}" data-social-link
                                class="social-circle">
                                <x-social-icon :network="$network" class="size-4" />
                                <span class="sr-only">{{ __('settings.social.'.$network) }} ({{ __('site.opens_in_new_tab') }})</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- زر المساعد العائم في الزاوية السفلية (partials/assistant): هامش سفلي بقدره، فلا يغطي رابط «سياسة الخصوصية» في آخر
         الصفحة (الملاحظة 6 من الاختبار الشامل 0.14) --}}
    @php($assistantOn = \App\Models\AssistantSettings::current()->is_enabled)
    <div @class([
        'mx-auto flex max-w-[82.5rem] flex-wrap items-center justify-between gap-3 border-t border-white/15 px-4 pt-4 text-sm sm:px-7',
        'pb-20' => $assistantOn,
        'pb-6' => ! $assistantOn,
    ]) data-footer-rights>
        <ul class="flex flex-wrap items-center gap-x-[18px] gap-y-1">
            @if (\App\Models\Page::isPublishedSlug(\App\Models\Page::PRIVACY))
                <li><a href="{{ route('privacy') }}" wire:navigate class="text-white/80 hover:text-white">{{ __('site.footer.privacy') }}</a></li>
            @endif
            <li><a href="{{ route('sitemap') }}" class="text-white/80 hover:text-white">{{ __('ui.footer.sitemap') }}</a></li>
        </ul>
        <p>{{ $text('rights') }} © {{ now()->year }} {{ __('site.name') }}</p>
        <button type="button" data-back-to-top data-static aria-label="{{ __('site.back_to_top') }}"
            class="inline-flex size-[2.875rem] items-center justify-center rounded-full border border-white/25 bg-primary text-white transition hover:bg-brand">
            {{ \App\Support\Icon::svg('chevron-up', 'size-[18px]', ['aria-hidden' => 'true']) }}
        </button>
    </div>
</footer>
