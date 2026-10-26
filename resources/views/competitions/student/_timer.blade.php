{{-- العداد الظاهر: الوقت الباقي من الخادم، ويعدّه resources/js/student-competition.js وينبّه في الدقائق الخمس الأخيرة.
     وأقصى تأخير عشوائي للتسليم الآلي نصف هامش الشبكة وحتى عشر ثوانٍ، فيقبله الخادم دائماً --}}
@php($left = $attempt->secondsLeft())
<div data-exam-timer="{{ $left }}" data-exam-auto-spread="{{ min(10, intdiv((int) config('competitions.student.grace_seconds'), 2)) }}" class="sticky top-2 z-10 flex items-center justify-between gap-3 rounded-2xl border border-line/70 bg-white px-5 py-3 shadow-xs data-[warning=true]:border-amber-300 data-[warning=true]:bg-amber-50">
    <span class="flex items-center gap-2 text-sm font-semibold text-ink">
        {{ svg('heroicon-o-clock', 'size-5 text-accent', ['aria-hidden' => 'true']) }}
        {{ __('competitions.student.time_left') }}
    </span>
    <span data-exam-timer-value role="timer" class="text-xl font-bold text-primary tabular-nums" dir="ltr">{{ sprintf('%02d:%02d', intdiv($left, 60), $left % 60) }}</span>
    <span data-exam-timer-alert data-message="{{ __('competitions.student.five_minutes') }}" role="status" class="sr-only"></span>
</div>
