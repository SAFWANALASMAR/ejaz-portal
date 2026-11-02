<?php

namespace App\Support\ProgramStats;

use App\Contracts\ProgramStatsSource;
use App\Enums\AttemptStatus;
use App\Enums\SubmissionStatus;
use App\Models\CompetitionSubmission;
use Illuminate\Database\Eloquent\Builder;

/**
 * صف «المسابقات» كلها في المسابقات المنشورة، والأشخاص لا المشاركات:
 * - المسجلون: كـ«مسجل في المسابقات» في الرئيسية (HomePortalStats): أصحاب مشاركات قائمة (الملغاة محذوفة حذفاً ناعماً) مسجَّلة
 *   (registered_at) في مسابقة الطلاب أو مرسلة في غيرها. فالمسودة التي لم تُرسل لا تُعدّ، والمسجل في مسابقتين يُعدّ مرة.
 * - المكملون: من أرسل مشاركته (submitted) في مسابقة عادية، أو سلّم محاولة اختبار (competition_attempts.status = submitted) في
 *   مسابقة الطلاب، فمشاركة الطالب تبقى مسودة بعد التسجيل والتسليم (RegisterStudent وSubmitAttempt). مرة لكل شخص.
 */
final class CompetitionsSource implements ProgramStatsSource
{
    /**
     * مفتاح محجوز: معرّف نوع البرنامج من اللوحة لا يبدأ بشرطة سفلية (ContentFields::slug)، فلا يحل نوع محل صف المسابقات.
     */
    public const KEY = '_competitions';

    public function rows(): array
    {
        $submissions = fn (): Builder => CompetitionSubmission::query()->whereHas('competition', fn (Builder $competition) => $competition->published());

        return [[
            'key' => self::KEY,
            'label' => __('program_stats.types.competitions'),
            'registered' => $submissions()
                ->where(fn (Builder $submission) => $submission->whereNotNull('registered_at')->orWhere('status', SubmissionStatus::Submitted->value))
                ->distinct()->count('user_id'),
            'completed' => $submissions()
                ->where(fn (Builder $submission) => $submission->where('status', SubmissionStatus::Submitted->value)
                    ->orWhereHas('attempts', fn (Builder $attempt) => $attempt->where('status', AttemptStatus::Submitted->value)))
                ->distinct()->count('user_id'),
            'pages' => null,
            'minutes' => null,
        ]];
    }
}
