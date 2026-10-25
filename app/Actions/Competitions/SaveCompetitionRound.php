<?php

namespace App\Actions\Competitions;

use App\Enums\AttemptStatus;
use App\Models\Competition;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\CompetitionAccess;
use App\Support\SchoolNetworkLimits;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * حفظ مرحلة في مسابقة الطلاب: الاسم، والترتيب، ونافذتها (يوم وساعة بداية ونهاية)، وعدد أسئلتها، ومدتها،
 * و«للمتأهلين». وبعد أول محاولة فيها لا تتغير البداية ولا العدد ولا المدة (الترتيب والموعد النهائي لكل طالب
 * مربوطان بها)، ويبقى تعديل الاسم ومد النهاية. والحذف قبل أول محاولة وحده (DeleteCompetitionRound).
 */
class SaveCompetitionRound
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Competition $competition, ?CompetitionRound $round, array $data): CompetitionRound
    {
        if (! CompetitionAccess::allows($actor, 'competitions.update') || ! $competition->student_mode || $competition->trashed()) {
            throw new AuthorizationException;
        }

        if ($round !== null && $round->competition_id !== $competition->id) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'question_count' => ['required', 'integer', 'min:'.config('competitions.student.min_questions'), 'max:'.config('competitions.student.max_questions')],
            'duration_minutes' => ['required', 'integer', 'min:'.config('competitions.student.min_minutes'), 'max:'.config('competitions.student.max_minutes')],
            'qualified_only' => ['boolean'],
        ], attributes: __('competitions.student.round_attributes'))->validate();

        $saved = DB::transaction(function () use ($competition, $round, $validated): CompetitionRound {
            $round = $round !== null
                ? CompetitionRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail()
                : new CompetitionRound;

            $endsAt = Carbon::parse((string) $validated['ends_at']);

            // بعد نشر النتائج لا تتغير النافذة: تمديدها يفتح الاختبار لطلاب تظهر درجتهم فور التسليم (مراجعة المشرف)
            if ($round->resultsPublished() && (! Carbon::parse((string) $validated['starts_at'])->equalTo($round->starts_at) || ! $endsAt->equalTo($round->ends_at))) {
                throw ValidationException::withMessages(['ends_at' => __('competitions.student.round_published_locked')]);
            }

            if ($round->locked()) {
                $changed = ! Carbon::parse((string) $validated['starts_at'])->equalTo($round->starts_at)
                    || (int) $validated['question_count'] !== $round->question_count
                    || (int) $validated['duration_minutes'] !== $round->duration_minutes
                    // ومن بدأ قُبل بشرط المرحلة حينها، فلا يتغير «للمتأهلين» بعده
                    || (bool) ($validated['qualified_only'] ?? false) !== $round->qualified_only;

                // والنهاية تُمد ولا تُقدَّم: المواعيد النهائية للمحاولات القائمة محسوبة منها، والنشر بعدها
                if ($changed || $endsAt->lt($round->ends_at)) {
                    throw ValidationException::withMessages(['starts_at' => __('competitions.student.round_locked')]);
                }
            }

            $round->competition_id = $competition->id;
            $round->fill([
                ...$validated,
                'sort_order' => (int) ($validated['sort_order'] ?? ($round->exists ? $round->sort_order : $competition->rounds()->max('sort_order') + 1)),
                'qualified_only' => (bool) ($validated['qualified_only'] ?? false),
            ])->save();

            // مدّ النهاية يمدّ المحاولات الجارية التي قطعتها النهاية القديمة: الموعد أقرب البدء + المدة والنهاية الجديدة
            // (مراجعة المشرف). والمسلَّمة لا تتغير
            foreach ($round->attempts()->where('status', AttemptStatus::InProgress)->lockForUpdate()->get() as $attempt) {
                $deadline = $attempt->started_at->copy()->addMinutes($round->duration_minutes)->min($round->ends_at);

                if (! $deadline->equalTo($attempt->deadline_at)) {
                    $attempt->deadline_at = $deadline;
                    $attempt->save();
                }
            }

            return $round;
        });

        // نافذة المسابقة تتغير، فتُحسب حدود شبكات المدارس من جديد (SchoolNetworkLimits)
        SchoolNetworkLimits::forget();

        return $saved;
    }
}
