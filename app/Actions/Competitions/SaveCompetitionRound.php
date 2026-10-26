<?php

namespace App\Actions\Competitions;

use App\Enums\AttemptStatus;
use App\Models\Competition;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\CompetitionAccess;
use App\Support\PublicImages;
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
            // الفرع أو المجال وصورته الاختيارية (مطوية «البراهين العلمية»). والصورة اسم عشوائي في مجلدها وحده
            'branch' => ['nullable', 'string', 'max:200'],
            'image_path' => ['nullable', 'string', 'max:255', 'regex:#^'.CompetitionRound::IMAGE_DIRECTORY.'/[A-Za-z0-9_-]+\.(jpe?g|png|webp)$#'],
        ], attributes: __('competitions.student.round_attributes'))->validate();

        $imagesBefore = $round?->publicImages()['image_path'] ?? [];
        $imagesAfter = filled($validated['image_path'] ?? null) ? [(string) $validated['image_path']] : [];
        PublicImages::prepare($imagesBefore, $imagesAfter, CompetitionRound::IMAGE_DIRECTORY, 'image_path');

        // النافذة بالدقيقة (حقل اللوحة بلا ثوانٍ): تُطبَّع عند الحفظ وتُقارن بالدقيقة، فلا يُعد حفظ بلا تغيير
        // تغييراً للبداية إن خُزنت بثوانٍ من غير اللوحة (مراجعة المشرف)
        $validated['starts_at'] = Carbon::parse((string) $validated['starts_at'])->startOfMinute();
        $validated['ends_at'] = Carbon::parse((string) $validated['ends_at'])->startOfMinute();

        $saved = DB::transaction(function () use ($competition, $round, $validated): CompetitionRound {
            $round = $round !== null
                ? CompetitionRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail()
                : new CompetitionRound;

            $startsAt = $validated['starts_at'];
            $endsAt = $validated['ends_at'];
            $startChanged = $round->exists && ! $startsAt->equalTo($round->starts_at->copy()->startOfMinute());
            $endChanged = $round->exists && ! $endsAt->equalTo($round->ends_at->copy()->startOfMinute());

            // بعد نشر النتائج لا تتغير النافذة: تمديدها يفتح الاختبار لطلاب تظهر درجتهم فور التسليم (مراجعة المشرف)
            if ($round->resultsPublished() && ($startChanged || $endChanged)) {
                throw ValidationException::withMessages(['ends_at' => __('competitions.student.round_published_locked')]);
            }

            if ($round->locked()) {
                $changed = $startChanged
                    || (int) $validated['question_count'] !== $round->question_count
                    || (int) $validated['duration_minutes'] !== $round->duration_minutes
                    // ومن بدأ قُبل بشرط المرحلة حينها، فلا يتغير «للمتأهلين» بعده
                    || (bool) ($validated['qualified_only'] ?? false) !== $round->qualified_only;

                // والنهاية تُمد ولا تُقدَّم: المواعيد النهائية للمحاولات القائمة محسوبة منها، والنشر بعدها
                if ($changed || ($endChanged && $endsAt->lt($round->ends_at))) {
                    throw ValidationException::withMessages(['starts_at' => __('competitions.student.round_locked')]);
                }
            }

            $extended = $endChanged && $endsAt->gt($round->ends_at);

            // وما لم يتغير بالدقيقة يبقى كما خُزن بثوانيه، فلا يقصّر الحفظُ المواعيدَ المحسوبة منه
            if ($round->exists) {
                $validated['starts_at'] = $startChanged ? $startsAt : $round->starts_at;
                $validated['ends_at'] = $endChanged ? $endsAt : $round->ends_at;
            }

            $round->competition_id = $competition->id;
            $round->fill([
                ...$validated,
                'sort_order' => (int) ($validated['sort_order'] ?? ($round->exists ? $round->sort_order : $competition->rounds()->max('sort_order') + 1)),
                'qualified_only' => (bool) ($validated['qualified_only'] ?? false),
            ])->save();

            // مدّ النهاية يمدّ المحاولات الجارية التي قطعتها النهاية القديمة: الموعد أقرب البدء + المدة والنهاية الجديدة
            // (مراجعة المشرف). والمسلَّمة لا تتغير. وبلا تمديد لا تُلمس، وهو أمر واحد في القاعدة بلا تحميل الصفوف: حفظ
            // المرحلة أثناء نافذتها لا يقفل آلاف المحاولات الجارية ولا يحمّلها (المراجعة السحابية، ع١)
            if ($extended) {
                $deadline = 'LEAST(started_at + make_interval(mins => ?::int), ?::timestamp)';
                $bindings = [$round->duration_minutes, $round->ends_at->toDateTimeString()];

                DB::update(
                    "UPDATE competition_attempts SET deadline_at = {$deadline}, updated_at = ? WHERE round_id = ? AND status = ? AND deadline_at < {$deadline}",
                    [...$bindings, now()->toDateTimeString(), $round->id, AttemptStatus::InProgress->value, ...$bindings],
                );
            }

            return $round;
        });

        // نافذة المسابقة تتغير، فتُحسب حدود شبكات المدارس من جديد (SchoolNetworkLimits)
        SchoolNetworkLimits::forget();
        PublicImages::discard($imagesBefore, $imagesAfter, CompetitionRound::IMAGE_DIRECTORY);

        return $saved;
    }
}
