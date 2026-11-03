{{--
    «شركاؤنا» تحت «البوابة بالأرقام» وقبل التذييل (أمر التصور النهائي، النسخة 63 — البند R17): شريط شعارات متحرك واحد (x-marquee)
    بالشركاء ثم الرعاة بترتيبهم في اللوحة، ويتوقف بالمرور والتركيز وزر الإيقاف ولمن طلب تقليل الحركة، و«عرض الكل» إلى صفحة الشركاء.
    والعنوان من «محتوى الرئيسية». ويُخفى من «إعدادات الموقع» (show_partners).
    وستة شعارات (192 بكسل وفاصل) تملأ عرض المحتوى على 1440، وما قلّ عنها يتكرر في المسار (repeat) ويتحرك أيضاً (إضافة توجيه المدير،
    3 نوفمبر 2026، البند ١١: «ما كان يتحرك يبقى يتحرك»؛ وكان صفاً ثابتاً بمراجعة المشرف في 2 نوفمبر).
--}}
@php
    $logos = $partners->concat($sponsors);
    $repeat = (int) ceil(6 / max(1, $logos->count()));
@endphp

<section class="home-band" data-band="{{ $band }}" aria-labelledby="home-partners">
    <div class="mx-auto max-w-[82.5rem] px-4 pt-6 pb-7 sm:px-7 lg:pt-7 lg:pb-8">
        <div class="mb-3.5 flex items-center justify-between gap-4">
            <x-section-heading id="home-partners" icon="handshake">{{ $title }}</x-section-heading>
            <a href="{{ route('media.partners') }}" wire:navigate class="more-link shrink-0">{{ __('site.view_all') }}<span class="sr-only">: {{ $title }}</span></a>
        </div>

        <x-marquee id="home-partners-track" :label="$title" :duration="max(25, $logos->count() * 5) * $repeat" :repeat="$repeat">
            {{-- الشعارات صوراً، والشريك بلا شعار مربع مؤقت باسمه بالحجم نفسه (إضافة توجيه المدير، 3 نوفمبر 2026، البند ١٨). والرابط يُفحص
                 عند العرض كبطاقة الشريك (media.partner)، ويفتح خارجياً بلا صلة بالصفحة --}}
            @foreach ($logos as $partner)
                @php($url = \App\Rules\SafeUrl::isSafe($partner->url, allowInternal: false) ? $partner->url : null)
                <{{ $url !== null ? 'a' : 'div' }} @if ($url !== null) href="{{ $url }}" target="_blank" rel="noopener noreferrer" @endif
                    class="partner-logo partner-strip-item card-lift" data-partner>
                    @if ($partner->logo)
                        <img src="{{ Storage::disk('public')->url($partner->logo) }}" alt="{{ $partner->name }}" width="192" height="80" loading="lazy" class="h-20 w-full object-contain" data-partner-logo>
                    @else
                        <span class="partner-placeholder" data-partner-placeholder>{{ $partner->name }}</span>
                    @endif
                    @if ($url !== null)
                        <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                    @endif
                </{{ $url !== null ? 'a' : 'div' }}>
            @endforeach
        </x-marquee>
    </div>
</section>
