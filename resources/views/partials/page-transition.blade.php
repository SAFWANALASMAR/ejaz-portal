{{--
    انتقال الصفحات بالشعار (أمر التصور النهائي، البند R25 — Final-Transition): طبقة بتدرج الهوية الداكن، وفي وسطها رمز الشعار هو
    مؤشر التحميل نفسه: نسخة باهتة، وفوقها نسخة بيضاء ساطعة تمتلئ من الأسفل إلى الأعلى (قناع يرتفع)، ولمعة عند الاكتمال، ثم
    تتلاشى. بلا خط تقدم منفصل ولا مكتبة. يظهرها page.js عند التنقّل السريع إن طال أكثر من 150 ملّي ثانية، وتبقى 300 على الأقل،
    وتُعلَن بـ aria-busy. ومع تقليل الحركة الشعار ثابت ساطع. والرمز من «محتوى الرئيسية» (HomeContent::brand)، فيتبع ما يُرفع.
--}}
@php($mark = \App\Support\HomeContent::brand('mark_white'))

@persist('page-transition')
    <div data-page-transition hidden role="status" aria-live="polite" aria-busy="false" class="page-transition"
        style="--transition-mark: url('{{ $mark }}')">
        <span class="page-transition-logo">
            <img src="{{ $mark }}" alt="" aria-hidden="true" class="page-transition-faint" width="495" height="505">
            <img src="{{ $mark }}" alt="" aria-hidden="true" class="page-transition-fill" width="495" height="505">
            <span class="page-transition-shine" aria-hidden="true"></span>
        </span>
        <span class="sr-only">{{ __('ui.transition.loading') }}</span>
    </div>
@endpersist
