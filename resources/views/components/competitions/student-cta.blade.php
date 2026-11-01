{{--
    زر التسجيل في مسابقة الطلاب بحالاته، مكوّن واحد للعمود الجانبي ولبطاقة الجوائز في بطل الصفحة فلا يتكرر منطقه: المسجل يرى
    رقمه المرجعي ومشاركاته، وغيره زر التسجيل في نافذته، والزائر الدخول أولاً، وقبل النافذة موعدها، وبعدها الإغلاق.
    hero: على الخلفية الداكنة بزر أبيض «شارك واربح» (Final-Competition) إلى الوجهة نفسها، بلا زر إنشاء الحساب (في العمود).
--}}
@props(['competition', 'submission' => null, 'status', 'hero' => false, 'register' => false])

@php
    use App\Enums\CompetitionStatus;
    use App\Support\Dates;

    $registerUrl = route('competitions.student.register', $competition->slug);
    $note = $hero ? 'text-sm leading-6 text-white/85' : 'text-sm text-muted';
@endphp

{{-- register: «بادر بالتسجيل» في بطل الصفحة (Final-Competition — البند P3) بمربع أيقونة كبير، إلى الوجهة نفسها، ولا يظهر إلا في
     حالة التسجيل المفتوح لمن لم يسجل، فالحالتان الأخريان (المسجل، وقبل النافذة أو بعدها) في بطاقة الجوائز بجانبه --}}
@if ($register)
    @if (! $submission?->registered_at && $status === CompetitionStatus::Open)
        <a href="{{ $registerUrl }}" @navigate($registerUrl) {{ $attributes->class('hero-register') }} data-student-register>
            <x-icon-tile icon="pencil-square" shade="medium" size="md" class="float-soft" data-loop />
            {{ __('ui.inner.register_now') }}
        </a>
    @endif
@else
<div {{ $attributes->class('space-y-4') }} data-student-cta>
    @if ($submission?->registered_at)
        <x-alert type="success">{{ __('competitions.student.registered_as', ['reference' => $submission->reference]) }}</x-alert>
        @if ($hero)
            <a href="{{ route('account.competitions') }}" @navigate(route('account.competitions')) class="hero-cta">{{ __('competitions.account.title') }}</a>
        @else
            <x-button :href="route('account.competitions')" variant="outline" class="w-full">{{ __('competitions.account.title') }}</x-button>
        @endif
    @elseif ($status === CompetitionStatus::Open)
        @if ($hero)
            {{-- للزائر وللمستفيد إلى صفحة التسجيل نفسها: تطلب الدخول وتحفظ وجهتها --}}
            <a href="{{ $registerUrl }}" @navigate($registerUrl) class="hero-cta">
                {{ __('ui.prize.cta') }}
                {{ \App\Support\Icon::svg('arrow-left', 'size-5 shrink-0', ['aria-hidden' => 'true']) }}
            </a>
        @else
            @auth
                <x-button :href="$registerUrl" class="w-full" arrow>{{ __('competitions.student.register_cta') }}</x-button>
            @else
                {{-- إلى صفحة التسجيل نفسها: تطلب الدخول وتحفظ وجهتها، فيعود إليها المستفيد بعد الدخول أو إنشاء الحساب (signup=1) لا إلى /account --}}
                <x-button :href="$registerUrl" class="w-full" arrow>{{ __('competitions.student.login_to_register') }}</x-button>
                <x-button :href="route('competitions.student.register', ['slug' => $competition->slug, 'signup' => 1])" variant="outline" class="w-full">{{ __('competitions.student.create_account') }}</x-button>
            @endauth
        @endif
    @elseif ($status === CompetitionStatus::Upcoming)
        <p class="{{ $note }}">{{ __('competitions.show.upcoming', ['date' => Dates::display($competition->opens_at, withTime: true)]) }}</p>
    @else
        <p class="{{ $note }}">{{ __('competitions.student.registration_closed') }}</p>
    @endif
</div>
@endif
