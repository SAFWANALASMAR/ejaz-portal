{{--
    صفحات اختبار الطالب بوضع التركيز (decisions/2026-11-06-exam-focus-mode.md)، من أول سؤال حتى التسليم: بلا رأس الموقع وقائمته،
    ولا شريط «حسابي» الجانبي، ولا شريط العودة ولا التذييل ولا المساعد (x-layouts.app بـ focus). وفي أعلاها شريط ثابت وحده:
    رمز الشعار الصغير، واسم المرحلة والمسابقة، وزر «ملء الشاشة»، والوقت الباقي. والأنماط في resources/css/exam-focus.css.
    وزر ملء الشاشة (ملاحظات المدير التنفيذي، 7 نوفمبر 2026 — decisions/2026-11-07-exam-fullscreen.md) مخفي حتى يظهره
    student-competition.js حيث يدعمه المتصفح، فلا يظهر على الآيفون ولا بلا سكربت، ويتبدل «تصغير الشاشة» داخلها.
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
                <button type="button" class="exam-fullscreen" data-exam-fullscreen hidden aria-pressed="false"
                    data-enter="{{ __('competitions.student.fullscreen_enter') }}" data-exit="{{ __('competitions.student.fullscreen_exit') }}">
                    {{ \App\Support\Icon::svg('arrows-out', 'exam-fullscreen-icon exam-fullscreen-enter') }}
                    {{ \App\Support\Icon::svg('arrows-in', 'exam-fullscreen-icon exam-fullscreen-exit') }}
                    <span data-exam-fullscreen-label class="max-sm:sr-only">{{ __('competitions.student.fullscreen_enter') }}</span>
                </button>
                @include('competitions.student._timer')
            </div>
        </div>

        <div class="exam-body">
            <x-flash />

            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
