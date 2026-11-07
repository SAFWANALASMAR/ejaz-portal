{{--
    صفحات اختبار الطالب بوضع التركيز (decisions/2026-11-06-exam-focus-mode.md)، من أول سؤال حتى التسليم: بلا رأس الموقع وقائمته،
    ولا شريط «حسابي» الجانبي، ولا شريط العودة ولا التذييل ولا المساعد (x-layouts.app بـ focus). وفي أعلاها شريط ثابت وحده:
    رمز الشعار الصغير، واسم المرحلة والمسابقة، والوقت الباقي. والأنماط في resources/css/exam-focus.css.
--}}
@props(['competition', 'round', 'attempt'])

<x-layouts.app :title="__('competitions.student.round_title', ['round' => $round->name, 'competition' => $competition->title])" noindex focus>
    <div class="exam-focus" data-exam-focus>
        <div class="exam-bar" data-exam-bar role="region" aria-label="{{ __('competitions.student.exam_bar') }}">
            <div class="exam-bar-inner">
                <img src="{{ \App\Support\HomeContent::brand('mark') }}" alt="" aria-hidden="true" width="495" height="505" class="exam-bar-mark">
                <div class="min-w-0 flex-1">
                    <h1 class="exam-bar-round">{{ $round->name }}</h1>
                    <p class="exam-bar-competition">{{ $competition->title }}</p>
                </div>
                @include('competitions.student._timer')
            </div>
        </div>

        <div class="exam-body">
            <x-flash />

            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
