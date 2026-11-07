{{-- العداد الظاهر: الوقت الباقي من الخادم، ويعدّه resources/js/student-competition.js وينبّه في الدقائق الخمس الأخيرة.
     وأقصى تأخير عشوائي للتسليم الآلي ربع هامش الشبكة وحتى خمس ثوانٍ: يبقى من الهامش ما يكفي تحميل الصفحة وطلب
     التسليم تحت حمل الذروة. وفي شريط الاختبار (x-layouts.exam): كبسولة زرقاء فاتحة بأيقونة الساعة في مربع الهوية والوقت
     بارزاً، ونبض خفيف في الدقائق الخمس الأخيرة يتوقف مع تقليل الحركة (resources/css/exam-focus.css) --}}
@php($left = $attempt->secondsLeft())
<div data-exam-timer="{{ $left }}" data-exam-auto-spread="{{ min(5, intdiv((int) config('competitions.student.grace_seconds'), 4)) }}" class="exam-timer">
    <x-icon-tile icon="clock" shade="light" size="xs" class="exam-timer-tile" />
    <span class="exam-timer-text">
        <span id="exam-timer-label" class="exam-timer-label">{{ __('competitions.student.time_left') }}</span>
        <span data-exam-timer-value role="timer" aria-labelledby="exam-timer-label" class="exam-timer-value" dir="ltr">{{ sprintf('%02d:%02d', intdiv($left, 60), $left % 60) }}</span>
    </span>
    <span data-exam-timer-alert data-message="{{ __('competitions.student.five_minutes') }}" role="status" class="sr-only"></span>
</div>
