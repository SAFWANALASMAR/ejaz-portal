<?php

namespace App\Actions\Competitions;

use App\Enums\CompetitionFieldType;
use App\Models\Competition;
use App\Models\CompetitionCategory;
use App\Models\CompetitionField;
use App\Models\CompetitionRegion;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Rules\SaudiMobile;
use App\Rules\SaudiNationalId;
use App\Support\Digits;
use App\Support\SaudiPhone;
use App\Support\SubmissionReference;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * تسجيل الطالب في مسابقة الطلاب (plans/student-competition.md): الاسم الرباعي، ورقم الهوية (مشفّراً،
 * وفريداً في المسابقة ببصمته)، والفئة من المفتوحة، والصف من صفوفها، والمنطقة من المفعّلة، وجوال ولي
 * الأمر اختيارياً (مشفّراً)، والحقول المخصصة، والتعهد. بحساب قائم بلا اشتراط جوال ولا بريد، وفي نافذة
 * التسجيل وحدها، وتسجيل واحد لكل حساب (القيد القائم). ولا تعديل بعده.
 */
class RegisterStudent
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Competition $competition, array $data): CompetitionSubmission
    {
        if (! $competition->student_mode || ! $competition->is_published || $competition->trashed() || ! $user->is_active) {
            throw new AuthorizationException;
        }

        if (! $competition->isOpen()) {
            throw new AuthorizationException(__('competitions.student.registration_closed'));
        }

        if ($competition->submissions()->withTrashed()->where('user_id', $user->id)->exists()) {
            throw new AuthorizationException(__('competitions.student.already_registered'));
        }

        foreach (['national_id', 'guardian_phone'] as $key) {
            if (is_string($data[$key] ?? null)) {
                $data[$key] = Digits::latin(trim($data[$key]));
            }
        }

        $categories = $competition->openCategories()->get()->keyBy('id');
        $category = $categories->get((int) (is_scalar($data['category_id'] ?? null) ? $data['category_id'] : 0));
        $grades = $category instanceof CompetitionCategory ? $category->gradeList() : [];
        $fields = $competition->fields()->get();

        $validated = Validator::make($data, [
            'full_name' => ['required', 'string', 'max:150', $this->fourPartName()],
            'national_id' => ['required', 'string', new SaudiNationalId],
            'category_id' => ['required', 'integer', Rule::in($categories->keys()->all())],
            'grade' => $grades === [] ? ['nullable', 'prohibited'] : ['required', 'string', Rule::in($grades)],
            'region_id' => ['required', 'integer', Rule::exists(CompetitionRegion::class, 'id')->where('is_active', true)],
            'guardian_phone' => ['nullable', 'string', 'max:20', new SaudiMobile],
            'fields' => ['nullable', 'array'],
            ...$this->fieldRules($fields->all()),
            'pledge' => ['accepted'],
        ], attributes: [
            ...__('competitions.student.attributes'),
            ...$fields->mapWithKeys(fn (CompetitionField $field): array => ['fields.'.$field->id => $field->label])->all(),
        ])->validate();

        $extra = $fields->mapWithKeys(function (CompetitionField $field) use ($validated): array {
            $value = $validated['fields'][$field->id] ?? null;

            return filled($value) ? [(string) $field->id => is_string($value) ? trim($value) : $value] : [];
        })->all();

        try {
            return DB::transaction(function () use ($user, $competition, $validated, $extra): CompetitionSubmission {
                $submission = new CompetitionSubmission;
                $submission->competition()->associate($competition);
                $submission->user()->associate($user);
                $submission->full_name = preg_replace('/\s+/u', ' ', trim((string) $validated['full_name']));
                $submission->national_id = (string) $validated['national_id'];
                $submission->national_id_hash = RegisterForExam::hash((string) $validated['national_id']);
                $submission->category_id = (int) $validated['category_id'];
                $submission->grade = filled($validated['grade'] ?? null) ? (string) $validated['grade'] : null;
                $submission->region_id = (int) $validated['region_id'];
                $submission->guardian_phone = filled($validated['guardian_phone'] ?? null) ? SaudiPhone::normalize((string) $validated['guardian_phone']) : null;
                $submission->extra = $extra === [] ? null : $extra;
                $submission->registered_at = now();
                $submission->save();

                SubmissionReference::assign($submission);

                return $submission;
            });
        } catch (UniqueConstraintViolationException) {
            // طلبان متزامنان من الحساب نفسه: الأول سجّل
            $raced = CompetitionSubmission::query()->withTrashed()->where('competition_id', $competition->id)->where('user_id', $user->id)->exists();

            if ($raced) {
                throw new AuthorizationException(__('competitions.student.already_registered'));
            }

            throw ValidationException::withMessages(['national_id' => __('competitions.exam.national_id_taken')]);
        }
    }

    /**
     * الاسم الرباعي: أربع كلمات فأكثر بحروف عربية أو لاتينية.
     */
    private function fourPartName(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $parts = preg_split('/\s+/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if (count($parts) < 4 || preg_match('/^[\p{Arabic}\p{Latin}\s\-\']+$/u', (string) $value) !== 1) {
                $fail(__('competitions.student.full_name_rule'));
            }
        };
    }

    /**
     * قواعد الحقول المخصصة بأنواعها، والاختيار من خياراته وحدها.
     *
     * @param  list<CompetitionField>  $fields
     * @return array<string, list<mixed>>
     */
    private function fieldRules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $rules['fields.'.$field->id] = [
                $field->is_required ? 'required' : 'nullable',
                ...match ($field->type) {
                    CompetitionFieldType::Text => ['string', 'max:200'],
                    CompetitionFieldType::Number => ['numeric', 'max_digits:20'],
                    CompetitionFieldType::Select => ['string', Rule::in($field->optionList())],
                },
            ];
        }

        return $rules;
    }
}
