{{--
    التذييل في كل التخطيطات (أمر التصور النهائي، النسخة 63 — البندان N3 وR17، وFinal-Home): ثلاثة مربعات متساوية بحشو 24 بكسل،
    لكل منها مربع أيقونة كبير بجانب عنوانه:
    - تواصل معنا: أربعة أسطر: البريد، والجوال، والعنوان (ورابط الخريطة)، وساعات العمل.
    - روابط سريعة: مربع واحد بعمودين، رابط في كل سطر بلا التفاف، بأسماء مختصرة (lang/ar/ui.php — footer.links).
    - الحسابات الرسمية: الشعار والمخطوطة في الوسط، وأيقونات الحسابات تحتهما.
    ثم سطر سفلي: سياسة الخصوصية وخريطة الموقع، والحقوق، وزر العودة للأعلى. و«شركاؤنا» خرجوا من التذييل إلى شريط في الرئيسية.
    نصوصه من «محتوى الرئيسية» (HomeContent، القسم footer)، وبيانات التواصل والحسابات من «إعدادات الموقع»، وما لم يُدخل لا يظهر.
    ولا رقم ولا بريد ولا شعار من التصور. ولا سطر إشراف (البند R2).
--}}
@php
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
    // الروابط السريعة بأسمائها المختصرة وأيقونات أقسامها. و«إنجازاتنا» تُضاف متى بُني مسارها (feature/achievements)
    $links = collect([
        'about' => ['about', 'building-library'],
        'programs' => ['programs.index', 'academic-cap'],
        'competitions' => ['competitions.index', 'trophy'],
        'library' => ['library.index', 'book-open'],
        'media' => ['media.index', 'newspaper'],
        'governance' => ['governance.index', 'scale'],
        'initiatives' => ['initiatives.index', 'sparkles'],
        'services' => ['services.index', 'squares-2x2'],
        'achievements' => ['achievements.index', 'trophy'],
        'contact' => ['contact', 'chat-bubble-left-right'],
    ])->filter(fn (array $link): bool => \Illuminate\Support\Facades\Route::has($link[0]));
@endphp

<footer class="site-footer text-white/85" data-site-footer>
    <div class="mx-auto grid max-w-[82.5rem] gap-[1.125rem] px-4 pt-8.5 pb-6 sm:px-7 lg:grid-cols-3">
        {{-- تواصل معنا --}}
        <section data-footer-box="contact" aria-labelledby="footer-contact" class="footer-box">
            <h2 id="footer-contact" class="footer-box-title"><x-icon-tile icon="envelope" size="md" class="footer-box-icon" />{{ $text('contact') }}</h2>
            <ul class="space-y-3 text-[15px]">
                @if ($email !== null)
                    <li class="flex items-center gap-2.5">
                        <x-icon-tile icon="envelope" size="xs" class="footer-line-icon" />
                        <a href="mailto:{{ $email }}" class="hover:text-white" dir="ltr">{{ $email }}</a>
                    </li>
                @endif
                @if ($phone !== null)
                    <li class="flex items-center gap-2.5">
                        <x-icon-tile icon="phone" size="xs" class="footer-line-icon" />
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="hover:text-white"><bdi dir="ltr">{{ $phone }}</bdi></a>
                    </li>
                @endif
                @if ($address !== null || $shortAddress !== null)
                    <li class="flex items-start gap-2.5">
                        <x-icon-tile icon="map-pin" size="xs" class="footer-line-icon mt-0.5" />
                        <span class="space-y-0.5 leading-6">
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
                <li class="flex items-center gap-2.5" data-footer-hours>
                    <x-icon-tile icon="clock" size="xs" class="footer-line-icon" />
                    <span>{{ $text('hours') }}</span>
                </li>
            </ul>
        </section>

        {{-- روابط سريعة: مربع واحد بعمودين --}}
        <nav data-footer-box="links" aria-labelledby="footer-links" class="footer-box">
            <h2 id="footer-links" class="footer-box-title"><x-icon-tile icon="link" size="md" class="footer-box-icon" />{{ $text('quick_links') }}</h2>
            <ul class="grid grid-cols-2 gap-3 text-sm">
                @foreach ($links as $key => [$route, $icon])
                    <li class="min-w-0">
                        <a href="{{ route($route) }}" @navigate(route($route)) class="footer-link-row">
                            <x-icon-tile :icon="$icon" size="xs" class="footer-line-icon" />
                            <span class="truncate">{{ __('ui.footer.links.'.$key) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- الحسابات الرسمية: الشعار والمخطوطة في الوسط، والحسابات تحتهما --}}
        <section data-footer-box="accounts" aria-labelledby="footer-accounts" class="footer-box">
            <h2 id="footer-accounts" class="footer-box-title"><x-icon-tile icon="share-network" size="md" class="footer-box-icon" />{{ $text('accounts') }}</h2>
            <x-brand light size="footer" class="justify-center self-center" />
            @if ($social !== [] || $whatsapp !== null)
                <ul class="flex flex-wrap justify-center gap-2" aria-label="{{ $text('channels') }}">
                    @foreach ($social as $network => $url)
                        <li>
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ __('settings.social.'.$network) }}" data-social-link
                                class="social-circle">
                                <x-social-icon :network="$network" class="size-4" />
                                <span class="sr-only">{{ __('settings.social.'.$network) }} ({{ __('site.opens_in_new_tab') }})</span>
                            </a>
                        </li>
                    @endforeach
                    @if ($whatsapp !== null && ! isset($social['whatsapp']))
                        <li>
                            <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" title="{{ __('contact.info.whatsapp') }}" data-whatsapp class="social-circle">
                                <x-social-icon network="whatsapp" class="size-4" />
                                <span class="sr-only">{{ __('contact.info.whatsapp') }} ({{ __('site.opens_in_new_tab') }})</span>
                            </a>
                        </li>
                    @endif
                </ul>
            @endif
        </section>
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
            class="inline-flex size-[2.875rem] items-center justify-center rounded-full border border-white/25 bg-primary text-white transition hover:bg-primary-deep">
            {{ \App\Support\Icon::svg('chevron-up', 'size-[18px]', ['aria-hidden' => 'true']) }}
        </button>
    </div>
</footer>
