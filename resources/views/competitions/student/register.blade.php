{{--
    تسجيل الطالب في مسابقة الطلاب: الاسم الرباعي، ورقم الهوية، والفئة والصف، والمنطقة، وجوال ولي الأمر
    اختيارياً، والحقول المخصصة، والتعهد. والخادم يعيد الفحص كله (RegisterStudent).
--}}
@php
    use App\Enums\CompetitionFieldType;
@endphp

<x-layouts.account :title="__('competitions.student.register_title', ['competition' => $competition->title])">
    <form method="POST" action="{{ route('competitions.student.register.store', $competition->slug) }}" data-once data-student-register
        class="space-y-5 rounded-2xl border border-line/70 bg-white p-6 shadow-xs">
        @csrf
        <h2 class="text-xl font-bold text-primary">{{ __('competitions.student.register_heading') }}</h2>
        <p class="text-sm text-muted">{{ __('competitions.student.register_intro') }}</p>

        <x-field name="full_name" :label="__('competitions.student.attributes.full_name')" :hint="__('competitions.student.full_name_hint')" required maxlength="150" autocomplete="name" />

        <x-field name="national_id" :label="__('competitions.student.attributes.national_id')" :hint="__('competitions.student.national_id_hint')" required inputmode="numeric" maxlength="10" dir="ltr" autocomplete="off" />

        <x-field name="category_id" type="select" :label="__('competitions.student.attributes.category_id')" required>
            <option value="">{{ __('competitions.exam.choose') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </x-field>

        {{-- الصفوف لكل فئة: يظهر الحقل بصفوف الفئة المختارة (student-competition.js)، وبلا سكربت تظهر الصفوف كلها مجمّعة --}}
        @php($withGrades = $categories->filter(fn ($category) => $category->gradeList() !== []))
        @if ($withGrades->isNotEmpty())
            <div data-grade-field>
            <x-field name="grade" type="select" :label="__('competitions.student.attributes.grade')" :hint="__('competitions.student.grade_hint')">
                <option value="">{{ __('competitions.exam.choose') }}</option>
                @foreach ($withGrades as $category)
                    <optgroup label="{{ $category->name }}" data-category="{{ $category->id }}">
                        @foreach ($category->gradeList() as $grade)
                            <option value="{{ $grade }}" @selected(old('grade') === $grade)>{{ $grade }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-field>
            </div>
        @endif

        <x-field name="gender" type="select" :label="__('competitions.student.attributes.gender')" required>
            <option value="">{{ __('competitions.exam.choose') }}</option>
            @foreach (\App\Enums\StudentGender::cases() as $gender)
                <option value="{{ $gender->value }}" @selected(old('gender') === $gender->value)>{{ $gender->getLabel() }}</option>
            @endforeach
        </x-field>

        <x-field name="region_id" type="select" :label="__('competitions.student.attributes.region_id')" required>
            @if ($regions->count() !== 1)
                <option value="">{{ __('competitions.exam.choose') }}</option>
            @endif
            @foreach ($regions as $region)
                <option value="{{ $region->id }}" @selected((string) old('region_id') === (string) $region->id)>{{ $region->name }}</option>
            @endforeach
        </x-field>

        <x-field name="guardian_phone" type="tel" :label="__('competitions.student.attributes.guardian_phone')" inputmode="tel" maxlength="20" dir="ltr" autocomplete="off" required />

        @foreach ($fields as $field)
            @php($label = $field->label.($field->is_required ? '' : ' ('.__('competitions.exam.optional').')'))
            @if ($field->type === CompetitionFieldType::Select)
                <x-field :name="'fields['.$field->id.']'" type="select" :label="$label" :required="$field->is_required">
                    <option value="">{{ __('competitions.exam.choose') }}</option>
                    @foreach ($field->optionList() as $option)
                        <option value="{{ $option }}" @selected(old('fields.'.$field->id) === $option)>{{ $option }}</option>
                    @endforeach
                </x-field>
            @else
                <x-field :name="'fields['.$field->id.']'" :type="$field->type === CompetitionFieldType::Number ? 'number' : 'text'" :label="$label" :required="$field->is_required"
                    :maxlength="$field->type === CompetitionFieldType::Text ? 200 : null" />
            @endif
        @endforeach

        {{-- ضوابط المشاركة من نصوص المسابقة (المطوية) قبل التعهد --}}
        @if ($competition->pageList('rules') !== [])
            <div class="rounded-xl bg-primary-soft p-4">
                <p class="font-semibold text-ink">{{ __('competitions.student.page.rules') }}</p>
                <ul class="mt-2 list-disc space-y-1 ps-5 text-sm leading-7 text-ink">
                    @foreach ($competition->pageList('rules') as $rule)
                        <li>{{ $rule }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="pledge" value="1" required class="mt-1 accent-primary" @checked(old('pledge'))>
                <span class="font-medium text-ink">{{ __('competitions.student.pledge') }}</span>
            </label>
            @error('pledge')
                <p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-bot-challenge action="competition-register" signed-in />

        <x-button class="w-full sm:w-auto" arrow>{{ __('competitions.student.register_submit') }}</x-button>
    </form>
</x-layouts.account>
