{{--
    «كيف تسجّل» (ملاحظة المدير التنفيذي، 29 أكتوبر 2026): خطوات التسجيل في برنامج كما تجري في البوابة، وزر يبدأ الخطوة
    التي تخص الزائر. في «الدورات والبرامج» بلا برنامج، وفي صفحة البرنامج ببرنامجه وتسجيل صاحب الحساب فيه.
    - الزائر في صفحة البرنامج: الدخول وإنشاء الحساب كلاهما إلى صفحة التسجيل في البرنامج، فتحفظ وجهتها ويعود إليها بعدهما
      (signup=1 يحوّل إلى إنشاء الحساب بدل الدخول — bootstrap/app.php).
    - صاحب الحساب: «سجّل الآن» بحالة البرنامج نفسها في بطاقته، أو «مسجّل» بحالة تسجيله، أو اختيار برنامج آخر.
    الأيقونات Heroicons حتى تصل مجموعة Phosphor (#95)، والاسم وحده في $steps يتغير عندها.
--}}
@props(['program' => null, 'registration' => null])

@php
    use App\Enums\ProgramStatus;
    use App\Support\AccountSettings;

    $steps = [
        'account' => 'user-plus',
        'choose' => 'squares-2x2',
        'press' => 'cursor-arrow-rays',
        'details' => 'pencil-square',
        'confirm' => 'envelope-open',
    ];

    $replace = [
        'button' => __('programs.show.register'),
        'waitlist' => __('programs.show.join_waitlist'),
        'confirm' => __('programs.register.confirm'),
        'mine' => __('programs.how_to.my_programs'),
    ];

    $open = $program?->status() === ProgramStatus::Open;
@endphp

<section data-programs-how-to aria-labelledby="programs-how-to-title" {{ $attributes->merge(['class' => 'rounded-2xl border border-line bg-white p-5 shadow-xs sm:p-8']) }}>
    <span class="block h-1 w-12 rounded-full bg-accent" aria-hidden="true"></span>
    <h2 id="programs-how-to-title" class="mt-3 text-2xl font-bold text-primary sm:text-3xl">{{ __('programs.how_to.title') }}</h2>
    <p class="mt-2 text-muted">{{ __('programs.how_to.intro') }}</p>

    <ol aria-label="{{ __('programs.how_to.steps_label') }}" @class([
        'mt-6 grid gap-4',
        'sm:grid-cols-2 lg:grid-cols-5' => $program === null,
        'sm:grid-cols-2' => $program !== null,
    ])>
        @foreach ($steps as $key => $icon)
            <li class="relative flex gap-3 rounded-xl bg-surface p-4 {{ $program === null ? 'lg:flex-col' : '' }}">
                <span class="relative inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-primary-soft text-brand" aria-hidden="true">
                    {{ svg('heroicon-o-'.$icon, 'size-6') }}
                    <span class="absolute -top-1 -end-1 inline-flex size-5 items-center justify-center rounded-full bg-accent text-xs font-bold text-white">{{ $loop->iteration }}</span>
                </span>
                <div class="min-w-0">
                    <h3 class="font-bold text-ink">
                        <span class="sr-only">{{ __('programs.how_to.step', ['number' => $loop->iteration]) }}:</span>
                        {{ __('programs.how_to.steps.'.$key.'.title', $replace) }}
                    </h3>
                    <p class="mt-1 text-sm leading-7 text-muted">{{ __('programs.how_to.steps.'.$key.'.body', $replace) }}</p>
                    @if ($key === 'account' && AccountSettings::verificationEnabled())
                        <p class="mt-1 text-sm leading-7 text-muted">{{ __('programs.how_to.steps.account.verify') }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    <div class="mt-6 flex flex-wrap items-center gap-3" role="group" aria-label="{{ __('programs.how_to.actions_label') }}">
        @guest
            {{-- في صفحة البرنامج إلى صفحة التسجيل فيه: تطلب الدخول وتحفظ وجهتها، فيعود إليها بعد الدخول أو إنشاء الحساب --}}
            <x-button data-how-to-start :href="$program ? route('programs.register', $program->slug) : route('login')" arrow>{{ __('programs.how_to.login') }}</x-button>
            <x-button data-how-to-start variant="outline" :href="$program ? route('programs.register', ['slug' => $program->slug, 'signup' => 1]) : route('register')">{{ __('programs.how_to.create_account') }}</x-button>
        @else
            @if ($program === null)
                <x-button data-how-to-start href="#programs-list" arrow>{{ __('programs.how_to.browse') }}</x-button>
            @elseif ($registration)
                <span class="inline-flex items-center gap-2 rounded-full bg-secondary-soft px-4 py-2 text-sm font-semibold text-secondary">
                    {{ svg('heroicon-o-check-circle', 'size-5') }}
                    {{ __('programs.how_to.registered', ['status' => $registration->status->getLabel()]) }}
                </span>
                <x-button data-how-to-start variant="outline" :href="route('account.programs.index')">{{ __('programs.how_to.my_programs') }}</x-button>
            @elseif ($open)
                <x-button data-how-to-start :href="route('programs.register', $program->slug)" arrow>
                    {{ $program->hasFreeSeat() ? __('programs.show.register') : __('programs.show.join_waitlist') }}
                </x-button>
            @else
                <p class="text-sm text-muted">{{ __('programs.how_to.unavailable') }}</p>
                <x-button data-how-to-start variant="outline" :href="route('programs.index')">{{ __('programs.how_to.browse_others') }}</x-button>
            @endif
        @endguest
    </div>
</section>
