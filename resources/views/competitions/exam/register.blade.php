{{--
    أولًا: صفحة التسجيل والتحقق من البيانات. زر المتابعة معطّل حتى تكتمل الحقول الإلزامية والتعهد
    (competition-exam.js)، والخادم يعيد الفحص كله (RegisterForExam).
--}}
@php
    use App\Enums\EducationStage;
    use App\Support\SaudiPhone;
@endphp

<x-layouts.account :title="__('competitions.exam.title', ['competition' => $competition->title])">
    @include('competitions.exam._steps', ['current' => 'registration'])

    <form method="POST" action="{{ route('competitions.exam.register', $competition->slug) }}" data-exam-gate
        class="space-y-5 concept-panel p-6">
        @csrf
        <h2 class="flex items-center gap-2 text-xl font-black text-primary-dark"><span class="heading-flank-dot" aria-hidden="true"></span>{{ __('competitions.exam.registration_heading') }}</h2>

        <x-field name="full_name" :label="__('competitions.exam.full_name')" required maxlength="150" autocomplete="name" />

        @if (config('competitions.exam.national_id'))
            <x-field name="national_id" :label="__('competitions.exam.national_id')" required inputmode="numeric" pattern="[0-9\u0660-\u0669]{10}" maxlength="10" dir="ltr" autocomplete="off" />
        @endif

        <x-field name="phone" :label="__('competitions.exam.phone')" :value="SaudiPhone::display($phone)" :hint="__('competitions.exam.phone_hint')" required readonly dir="ltr" />

        <x-field name="contact_email" type="email" :label="__('competitions.exam.contact_email').' ('.__('competitions.exam.optional').')'" :value="$email" maxlength="255" dir="ltr" autocomplete="email" />

        <x-field name="education_stage" type="select" :label="__('competitions.exam.education_stage')" required>
            <option value="">{{ __('competitions.exam.choose') }}</option>
            @foreach (EducationStage::cases() as $stage)
                <option value="{{ $stage->value }}" @selected(old('education_stage') === $stage->value)>{{ $stage->getLabel() }}</option>
            @endforeach
        </x-field>

        <div>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="pledge" value="1" required class="mt-1 accent-primary" @checked(old('pledge'))>
                <span class="font-medium text-ink">{{ __('competitions.exam.pledge') }}</span>
            </label>
            @error('pledge')
                <p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-button data-exam-gate-submit disabled arrow>{{ __('competitions.exam.continue') }}</x-button>
    </form>
</x-layouts.account>
