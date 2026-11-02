{{--
    «شركاؤنا» تحت «البوابة بالأرقام» وقبل التذييل (أمر التصور النهائي، النسخة 63 — البند R17): شريط شعارات متحرك واحد (x-marquee)
    بالشركاء ثم الرعاة بترتيبهم في اللوحة، ويتوقف بالمرور والتركيز وزر الإيقاف ولمن طلب تقليل الحركة، و«عرض الكل» إلى صفحة الشركاء.
    والعنوان من «محتوى الرئيسية». ويُخفى من «إعدادات الموقع» (show_partners).
    وأقل من ستة شعارات (192 بكسل وفاصل، نحو عرض المحتوى على 1440) صف ثابت في الوسط بلا تكرار ولا زر إيقاف، فلا يتكرر الشعار الواحد متحركاً.
--}}
@php
    $logos = $partners->concat($sponsors);
@endphp

<section class="home-band" data-band="{{ $band }}" aria-labelledby="home-partners">
    <div class="mx-auto max-w-[82.5rem] px-4 pt-6 pb-7 sm:px-7 lg:pt-7 lg:pb-8">
        <div class="mb-3.5 flex items-center justify-between gap-4">
            <x-section-heading id="home-partners" icon="handshake">{{ $title }}</x-section-heading>
            <a href="{{ route('media.partners') }}" wire:navigate class="more-link shrink-0">{{ __('site.view_all') }}</a>
        </div>

        <x-marquee id="home-partners-track" :label="$title" :duration="max(25, $logos->count() * 5)" :static="$logos->count() < 6">
            @foreach ($logos as $partner)
                <div class="partner-logo">@include('media.partner', ['partner' => $partner])</div>
            @endforeach
        </x-marquee>
    </div>
</section>
