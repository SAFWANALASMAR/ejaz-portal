<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Models\CompetitionCategory;
use App\Models\CompetitionRegion;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\CompetitionAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * تصحيح تسجيل في مسابقة الطلاب (مراجعة المطابقة): للمدير التنفيذي والمسؤول التقني وحدهما
 * (competition.correct_registrations)، بسبب مكتوب يُسجَّل في سجل العمليات بلا رقم الهوية:
 * - تحرير: الاسم، والفئة من المفتوحة، والصف من صفوفها، والمنطقة من المفعّلة.
 * - إلغاء: يُحذف التسجيل حذفاً ناعماً ويُفك رقم الهوية (يُمسح الرقم وبصمته)، فيسجّل صاحبه الحقيقي إن كان
 *   الرقم محجوزاً بغير حق، ويخرج الملغى من النتائج. والحساب الملغى تسجيله لا يسجّل في المسابقة نفسها ثانية.
 */
class CorrectStudentRegistration
{
    /**
     * @param  array<string, mixed>  $data  {reason, full_name?, category_id?, grade?, region_id?}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function edit(User $actor, CompetitionSubmission $submission, array $data): CompetitionSubmission
    {
        $this->authorize($actor, $submission);

        $category = CompetitionCategory::query()->find((int) ($data['category_id'] ?? 0));
        $grades = $category?->gradeList() ?? [];

        $validated = Validator::make($data, [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'category_id' => ['required', 'integer', Rule::in($submission->competition->openCategories()->pluck('competition_categories.id')->all())],
            'grade' => $grades === [] ? ['nullable', 'prohibited'] : ['required', 'string', Rule::in($grades)],
            'region_id' => ['required', 'integer', Rule::exists(CompetitionRegion::class, 'id')->where('is_active', true)],
        ], attributes: [...__('competitions.student.attributes'), 'reason' => __('competitions.student.admin.correction_reason')])->validate();

        return DB::transaction(function () use ($actor, $submission, $validated): CompetitionSubmission {
            $locked = CompetitionSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['full_name', 'category_id', 'grade', 'region_id']);

            $locked->full_name = preg_replace('/\s+/u', ' ', trim((string) $validated['full_name']));
            $locked->category_id = (int) $validated['category_id'];
            $locked->grade = filled($validated['grade'] ?? null) ? (string) $validated['grade'] : null;
            $locked->region_id = (int) $validated['region_id'];
            $locked->save();

            $this->log($actor, $locked, 'edit', (string) $validated['reason'], array_keys(array_diff_assoc(
                array_map('strval', $locked->only(['full_name', 'category_id', 'grade', 'region_id'])),
                array_map('strval', $before),
            )));

            return $locked;
        });
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function cancel(User $actor, CompetitionSubmission $submission, string $reason): void
    {
        $this->authorize($actor, $submission);
        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('competitions.student.admin.correction_reason_rule')]);
        }

        DB::transaction(function () use ($actor, $submission, $reason): void {
            $locked = CompetitionSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $locked->national_id = null;
            $locked->national_id_hash = null;
            $locked->guardian_phone = null;
            $locked->save();
            $locked->delete();

            $this->log($actor, $locked, 'cancel', $reason, ['national_id']);
        });
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(User $actor, CompetitionSubmission $submission): void
    {
        if (! CompetitionAccess::allows($actor, 'competition.correct_registrations') || ! $submission->competition->student_mode || $submission->trashed()) {
            throw new AuthorizationException;
        }
    }

    /**
     * @param  list<string>  $fields  أسماء الحقول المتغيرة لا قيمها
     */
    private function log(User $actor, CompetitionSubmission $submission, string $mode, string $reason, array $fields): void
    {
        activity('competitions')->performedOn($submission)->causedBy($actor)
            ->event(ActivityEvent::RegistrationCorrected->value)
            ->withProperties(['mode' => $mode, 'reason' => trim($reason), 'fields' => $fields, 'reference' => $submission->reference])
            ->log(ActivityEvent::RegistrationCorrected->value);
    }
}
