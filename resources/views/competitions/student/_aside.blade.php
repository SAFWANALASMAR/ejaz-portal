{{-- بطاقة التسجيل في مسابقة الطلاب: المسجل يرى رقمه المرجعي، وغيره زر التسجيل في نافذته، والزائر الدخول أولاً --}}
@php
    use App\Enums\CompetitionStatus;
    use App\Support\Dates;
@endphp

@if ($submission?->registered_at)
    <x-alert type="success">{{ __('competitions.student.registered_as', ['reference' => $submission->reference]) }}</x-alert>
    <x-button :href="route('account.competitions')" variant="outline" class="w-full">{{ __('competitions.account.title') }}</x-button>
@elseif ($status === CompetitionStatus::Open)
    @auth
        <x-button :href="route('competitions.student.register', $competition->slug)" class="w-full" arrow>{{ __('competitions.student.register_cta') }}</x-button>
    @else
        {{-- إلى صفحة التسجيل نفسها: تطلب الدخول وتحفظ وجهتها، فيعود إليها المستفيد بعد الدخول لا إلى /account --}}
        <x-button :href="route('competitions.student.register', $competition->slug)" class="w-full" arrow>{{ __('competitions.student.login_to_register') }}</x-button>
        <x-button :href="route('register')" variant="outline" class="w-full">{{ __('competitions.student.create_account') }}</x-button>
    @endauth
@elseif ($status === CompetitionStatus::Upcoming)
    <p class="text-sm text-muted">{{ __('competitions.show.upcoming', ['date' => Dates::display($competition->opens_at, withTime: true)]) }}</p>
@else
    <p class="text-sm text-muted">{{ __('competitions.student.registration_closed') }}</p>
@endif
