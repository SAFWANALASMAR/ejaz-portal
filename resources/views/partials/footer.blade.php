{{--
    التذييل بهوية الجمعية (مراجعة المشرف، 26 أكتوبر 2026 — docs/design/ui-reference.md «التذييل»): شريط علوي بالشعار والمخطوطة
    ونبذة الجمعية والإشراف والترخيص، ثم أعمدة الروابط المختصرة والتواصل (ومعه العنوان الوطني برابط الخريطة) والقنوات بشعارات
    منصاتها، ثم الحقوق. نصوصه من «محتوى الرئيسية» (HomeContent، القسم footer)، وبيانات التواصل والقنوات من «إعدادات الموقع»،
    وما لم يُدخل لا يظهر.
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
@endphp

{{-- بخلفية القسم الداكن ودوائره وزخرفته الهندسية (التحسين البصري — docs/plans/visual-refresh.md) --}}
<footer class="section-dark" data-site-footer>
    <div class="bg-pattern-light pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
    <span class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-linear-to-l from-accent-light via-brand to-secondary" aria-hidden="true"></span>
    <img src="{{ asset('images/decor/palms-line.svg') }}" alt="" aria-hidden="true"
        class="pointer-events-none absolute bottom-0 end-0 w-[420px] max-w-[70%] opacity-15">

    <div class="container-page relative">
        {{-- الهوية: الشعار والمخطوطة، والنبذة، والإشراف والترخيص --}}
        <div class="flex flex-col gap-6 border-b border-white/10 py-10 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-4">
                <x-brand light />
                <p class="max-w-xl text-sm leading-7 text-white/80">{{ $text('about') }}</p>
            </div>
            <div class="flex flex-col gap-2 text-sm lg:items-end">
                <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-accent-light ring-1 ring-white/15">
                    {{ \App\Support\Icon::svg('academic-cap', 'size-4 shrink-0', ['aria-hidden' => 'true']) }}
                    {{ $text('supervision') }}
                </p>
                <p class="text-xs text-white/75">{{ $text('license') }}</p>
            </div>
        </div>

        <div class="grid gap-10 py-10 sm:grid-cols-2 lg:grid-cols-[1.2fr_1.3fr_1fr]">
            <nav aria-labelledby="footer-links">
                <p id="footer-links" class="mb-4 flex items-center gap-2 font-bold text-accent-light">
                    <span class="h-0.5 w-6 rounded-full bg-accent-light" aria-hidden="true"></span>{{ $text('quick_links') }}
                </p>
                <ul class="grid grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                    {{-- العنصر الذي يشير إلى صفحة غير منشورة يبقى في الرأس لعناصره الفرعية، ولا رابط له هنا (SiteChromeComposer) --}}
                    @foreach ($menu->where('linkable', true) as $item)
                        <li>
                            <a href="{{ $item->href() }}" @if ($item->opensInNewTab) target="_blank" rel="noopener" @endif @if ($item->navigates()) wire:navigate @endif
                                class="inline-flex items-center gap-1.5 text-white/80 transition hover:text-white">
                                {{ \App\Support\Icon::svg('chevron-left', 'size-3 shrink-0 text-accent-light', ['aria-hidden' => 'true']) }}
                                {{ $item->label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            @if ($hasContact)
                <div class="text-sm">
                    <p class="mb-4 flex items-center gap-2 font-bold text-accent-light">
                        <span class="h-0.5 w-6 rounded-full bg-accent-light" aria-hidden="true"></span>{{ $text('contact') }}
                    </p>
                    <ul class="space-y-3">
                        @if ($email !== null)
                            <li class="flex items-center gap-3">
                                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-white/10">{{ \App\Support\Icon::svg('envelope', 'size-4 text-accent-light', ['aria-hidden' => 'true']) }}</span>
                                <a href="mailto:{{ $email }}" class="text-white/85 hover:text-white" dir="ltr">{{ $email }}</a>
                            </li>
                        @endif
                        @if ($phone !== null)
                            <li class="flex items-center gap-3">
                                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-white/10">{{ \App\Support\Icon::svg('phone', 'size-4 text-accent-light', ['aria-hidden' => 'true']) }}</span>
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="text-white/85 hover:text-white"><bdi dir="ltr">{{ $phone }}</bdi></a>
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
                            <li class="flex items-start gap-3">
                                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-white/10">{{ \App\Support\Icon::svg('map-pin', 'size-4 text-accent-light', ['aria-hidden' => 'true']) }}</span>
                                <span class="space-y-1 leading-7 text-white/85">
                                    @if ($address !== null)
                                        <span class="block">{{ $address }}</span>
                                    @endif
                                    @if ($shortAddress !== null)
                                        <span class="block text-white/75">{{ __('settings.contact.short_address') }}: <bdi dir="ltr">{{ $shortAddress }}</bdi></span>
                                    @endif
                                    @if ($map !== null)
                                        <a href="{{ $map }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-semibold text-accent-light hover:text-white">
                                            {{ __('contact.info.map') }}
                                            {{ \App\Support\Icon::svg('arrow-up-left', 'size-3.5', ['aria-hidden' => 'true']) }}
                                            <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                                        </a>
                                    @endif
                                </span>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif

            @if ($social !== [])
                <div>
                    <p class="mb-4 flex items-center gap-2 font-bold text-accent-light">
                        <span class="h-0.5 w-6 rounded-full bg-accent-light" aria-hidden="true"></span>{{ $text('channels') }}
                    </p>
                    <ul class="flex flex-wrap gap-2.5">
                        @foreach ($social as $network => $url)
                            <li>
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ __('settings.social.'.$network) }}"
                                    class="inline-flex size-10 items-center justify-center rounded-full bg-white text-primary-dark shadow-sm transition hover:-translate-y-0.5 hover:bg-accent-light">
                                    <x-social-icon :network="$network" class="size-5" />
                                    <span class="sr-only">{{ __('settings.social.'.$network) }} ({{ __('site.opens_in_new_tab') }})</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <div class="relative border-t border-white/10 bg-black/10">
        {{-- زر المساعد العائم في الزاوية السفلية (partials/assistant): هامش سفلي بقدره، فلا يغطي رابط «سياسة الخصوصية» في آخر
             الصفحة (الملاحظة 6 من الاختبار الشامل 0.14) --}}
        @php($assistantOn = \App\Models\AssistantSettings::current()->is_enabled)
        <div @class([
            'container-page flex flex-col gap-2 pt-5 text-xs text-white/75 sm:flex-row sm:items-center sm:justify-between',
            'pb-20' => $assistantOn,
            'pb-5' => ! $assistantOn,
        ]) data-footer-rights>
            <p>© {{ now()->year }} {{ __('site.name') }} — {{ $text('rights') }}</p>
            @if (\App\Models\Page::isPublishedSlug(\App\Models\Page::PRIVACY))
                <a href="{{ route('privacy') }}" wire:navigate class="hover:text-white">{{ __('site.footer.privacy') }}</a>
            @endif
        </div>
    </div>
</footer>
