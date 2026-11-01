{{--
    انتقال الصفحات بالشعار (أمر التصور النهائي، البند R25 — Final-Transition): طبقة بتدرج الهوية الداكن، وفي وسطها رمز الشعار هو
    مؤشر التحميل نفسه: نسخة باهتة، وفوقها نسخة بيضاء ساطعة تمتلئ من الأسفل إلى الأعلى (قناع يرتفع)، ولمعة عند الاكتمال، ثم
    تتلاشى. وتحت الرمز مخطوطة اسم الجمعية كبيرة (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ١٣). بلا خط تقدم منفصل ولا مكتبة.
    يظهرها page.js عند التنقّل السريع إن طال أكثر من 400 ملّي ثانية وحدها، وتتلاشى فور حلول الصفحة الجديدة (البندان ٥ و٢١)،
    وتُعلَن بـ aria-busy. ومع تقليل الحركة الشعار ثابت ساطع. والرمز والمخطوطة من «محتوى الرئيسية» (HomeContent::brand)، فيتبعان
    ما يُرفع. وفي صفحات التنقّل السريع وحدها (SpaLink::currentPage، كسكربت Livewire في التخطيط)، فلا يُحمَّل Livewire في غيرها.
--}}
@if (\App\Support\SpaLink::currentPage(request()))
    @php($mark = \App\Support\HomeContent::brand('mark_white'))

    @persist('page-transition')
        <div data-page-transition hidden role="status" aria-live="polite" aria-busy="false" class="page-transition"
            style="--transition-mark: url('{{ $mark }}')">
            <span class="page-transition-logo">
                <img src="{{ $mark }}" alt="" aria-hidden="true" class="page-transition-faint" width="495" height="505">
                <img src="{{ $mark }}" alt="" aria-hidden="true" class="page-transition-fill" width="495" height="505">
                <span class="page-transition-shine" aria-hidden="true"></span>
            </span>
            <img src="{{ \App\Support\HomeContent::brand('calligraphy_white') }}" alt="" aria-hidden="true" data-transition-calligraphy
                class="page-transition-calligraphy" width="1218" height="185">
            <span class="sr-only">{{ __('ui.transition.loading') }}</span>
        </div>
    @endpersist
@endif
