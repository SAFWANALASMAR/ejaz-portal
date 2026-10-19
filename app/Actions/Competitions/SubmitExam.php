<?php

namespace App\Actions\Competitions;

use App\Enums\ExamStage;
use App\Enums\SubmissionStatus;
use App\Jobs\GradeSubmission;
use App\Jobs\SendExamReceipt;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Notifications\SubmissionReceived;
use App\Services\Messaging\MessagingSettings;
use App\Support\ExamQuestions;
use App\Support\SubmissionReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «إرسال وإنهاء الاختبار»: يقفل صف المشاركة، ويفحص أن الأسئلة الإلزامية مجابة، ثم يقفلها برقم مرجعي،
 * ويرسل التصحيح الآلي والتأكيد إلى الطابور (وواتساب إن فُعّل). والإرسال متكرر الأمان: الطلب الثاني
 * ينتظر الأول ثم يجدها مرسلة فيعيدها كما هي، بلا خطأ ولا إشعار ثانٍ. ولا تعديل بعده.
 */
class SubmitExam
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, CompetitionSubmission $submission): CompetitionSubmission
    {
        if ($submission->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $competition = $submission->competition;
        ExamQuestions::load($competition);

        /** @var array{0: CompetitionSubmission, 1: bool} $result */
        $result = DB::transaction(function () use ($submission, $competition): array {
            /** @var CompetitionSubmission $locked */
            $locked = CompetitionSubmission::query()->withTrashed()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

            if ($locked->trashed() || ! $competition->exam_mode) {
                throw new AuthorizationException;
            }

            if (! $locked->isDraft()) {
                return [$locked, false];
            }

            if (! $competition->isOpen()) {
                throw new AuthorizationException(__('competitions.entry.closed'));
            }

            if ($locked->examStage() !== ExamStage::Questions) {
                throw new AuthorizationException(__('competitions.exam.wrong_stage'));
            }

            $missing = $competition->questions
                ->filter(fn (CompetitionQuestion $question): bool => $question->is_required && $locked->answer($question) === null)
                ->count();

            if ($missing > 0) {
                throw ValidationException::withMessages(['answers' => trans_choice('competitions.exam.unanswered_required', $missing, ['count' => $missing])]);
            }

            $locked->status = SubmissionStatus::Submitted;
            $locked->submitted_at = now();
            SubmissionReference::assign($locked);

            return [$locked, true];
        });

        [$locked, $fresh] = $result;

        if ($fresh) {
            GradeSubmission::dispatch($locked->id)->afterCommit();
            $user->notify(new SubmissionReceived($locked));

            if (MessagingSettings::usageEnabled('whatsapp', 'exam_receipt')) {
                SendExamReceipt::dispatch($locked->id)->afterCommit();
            }
        }

        return $locked;
    }
}
