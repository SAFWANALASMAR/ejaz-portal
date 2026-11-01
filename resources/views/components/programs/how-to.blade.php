{{--
    «كيف تسجّل» (ملاحظة المدير التنفيذي، 29 أكتوبر 2026): خطوات التسجيل في برنامج كما تجري في البوابة، وزر يبدأ الخطوة
    التي تخص الزائر. في «الدورات والبرامج» بلا برنامج، وفي صفحة البرنامج ببرنامجه وحالته وعدد مؤكديه وتسجيل صاحب الحساب
    فيه، كما حسبتها الصفحة لبطاقتها (programs/show.blade.php)، فلا يفترق الزران.
    - الزائر والبرنامج مفتوح: الدخول وإنشاء الحساب كلاهما إلى صفحة التسجيل في البرنامج، فتحفظ وجهتها ويعود إليها بعدهما
      (signup=1 يحوّل إلى إنشاء الحساب بدل الدخول — bootstrap/app.php).
    - صاحب الحساب: «سجّل الآن» أو قائمة الانتظار، أو «مسجّل» بحالة تسجيله، أو اختيار برنامج آخر حين يُغلق التسجيل.
    الأيقونات Heroicons حتى تصل مجموعة Phosphor (#95)، والاسم وحده في $steps يتغير عندها. والمعرّف ثابت: مكوّن واحد في الصفحة.
--}}
@props(['program' => null, 'registration' => null, 'status' => null, 'confirmed' => null])

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
        'login' => __('programs.show.login_to_register'),
        'confirm' => __('programs.register.confirm'),
        'mine' => __('programs.mine.title'),
    ];

    $open = ($status ?? $program?->status()) === ProgramStatus::Open;
@endphp

<section data-programs-how-to aria-labelledby="programs-how-to-title" {{ $attributes->merge(['class' => 'concept-panel p-5 sm:p-8']) }}>
    {{-- عنوان القسم بالنمط الموحد (x-section-heading)، ومعرّفه على العنوان نفسه لـ aria-labelledby --}}
    <h2 id="programs-how-to-title" class="section-heading-title flex items-center gap-2.5 font-black leading-tight text-primary-dark">
        <x-icon-tile icon="clipboard-document-check" shade="medium" size="sm" class="section-heading-icon" data-loop />
        <span>{{ __('programs.how_to.title') }}</span>
    </h2>
    <p class="mt-2 text-[var(--concept-text)]">{{ __('programs.how_to.intro') }}</p>

    {{-- role="list": سفاري يُسقط دلالة القائمة عن ما بلا علامات --}}
    <ol role="list" aria-label="{{ __('programs.how_to.steps_label') }}" @class([
        'mt-6 grid gap-4',
        'sm:grid-cols-2 lg:grid-cols-5' => $program === null,
        'sm:grid-cols-2' => $program !== null,
    ])>
        @foreach ($steps as $key => $icon)
            <li @class(['tone-card relative flex gap-3 p-4', 'lg:flex-col' => $program === null])>
                <span class="relative shrink-0" aria-hidden="true">
                    <x-icon-tile :icon="$icon" size="sm" />
                    <span class="tone-chip absolute -top-1.5 -end-1.5 px-1.5! text-xs">{{ $loop->iteration }}</span>
                </span>
                <div class="min-w-0">
                    <h3 class="font-black">
                        <span class="sr-only">{{ __('programs.how_to.step', ['number' => $loop->iteration]) }}:</span>
                        {{ __('programs.how_to.steps.'.$key.'.title', $replace) }}
                    </h3>
                    <p class="tone-card-muted mt-1 text-sm leading-7">{{ __('programs.how_to.steps.'.$key.'.body', $replace) }}</p>
                    @if ($key === 'account' && AccountSettings::verificationEnabled())
                        <p class="tone-card-muted mt-1 text-sm leading-7">{{ __('programs.how_to.steps.account.verify') }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    <div class="mt-6 flex flex-wrap items-center gap-3" role="group" aria-label="{{ __('programs.how_to.actions_label') }}">
        @if ($program === null)
            @guest
                <x-button data-how-to-start :href="route('login')" arrow>{{ __('programs.how_to.login') }}</x-button>
                <x-button data-how-to-start variant="outline" :href="route('register')">{{ __('programs.how_to.create_account') }}</x-button>
            @else
                <x-button data-how-to-start href="#programs-list" arrow>{{ __('programs.how_to.browse') }}</x-button>
            @endguest
        @elseif ($registration)
            <span class="inline-flex items-center gap-2 rounded-full bg-secondary-soft px-4 py-2 text-sm font-semibold text-secondary">
                {{ svg('heroicon-o-check-circle', 'size-5') }}
                {{ __('programs.how_to.registered', ['status' => $registration->status->getLabel()]) }}
            </span>
            <x-button data-how-to-start variant="outline" :href="route('account.programs.index')">{{ __('programs.mine.title') }}</x-button>
        @elseif (! $open)
            <p class="text-sm text-muted">{{ __('programs.show.closed') }}</p>
            <x-button data-how-to-start variant="outline" :href="route('programs.index')">{{ __('programs.how_to.browse_others') }}</x-button>
        @else
            @guest
                {{-- إلى صفحة التسجيل في البرنامج: تطلب الدخول وتحفظ وجهتها، فيعود إليها بعد الدخول أو إنشاء الحساب --}}
                <x-button data-how-to-start :href="route('programs.register', $program->slug)" arrow>{{ __('programs.how_to.login') }}</x-button>
                <x-button data-how-to-start variant="outline" :href="route('programs.register', ['slug' => $program->slug, 'signup' => 1])">{{ __('programs.how_to.create_account') }}</x-button>
            @else
                <x-button data-how-to-start :href="route('programs.register', $program->slug)" arrow>
                    {{ $program->hasFreeSeat($confirmed) ? __('programs.show.register') : __('programs.show.join_waitlist') }}
                </x-button>
            @endguest
        @endif
    </div>
</section>
