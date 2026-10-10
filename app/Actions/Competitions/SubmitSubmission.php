<?php

namespace App\Actions\Competitions;

use App\Enums\QuestionType;
use App\Enums\SubmissionStatus;
use App\Enums\VideoStatus;
use App\Jobs\GradeSubmission;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionSubmission;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Notifications\SubmissionReceived;
use App\Support\SubmissionReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إرسال المشاركة: يحفظ آخر الإجابات، ثم يفحص الإلزامي (الفئة والمنطقة والأسئلة، وكل فيديو إلزامي
 * جاهز)، ثم يقفلها برقم مرجعي فريد، ثم يرسل التصحيح الآلي والتأكيد إلى الطابور. ولا تعديل بعده.
 */
class SubmitSubmission
{
    public function __construct(private SaveSubmissionDraft $saveDraft) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, CompetitionSubmission $submission, array $data): CompetitionSubmission
    {
        $this->saveDraft->handle($user, $submission, $data);

        DB::transaction(function () use ($submission): void {
            // طلبان متزامنان للإرسال: الثاني ينتظر الأول، ثم يجدها مرسلة فيرفض. والفحص بعد القفل،
            // فلا يغيّر حفظ تلقائي متزامن إجابة بين الفحص والإرسال
            $locked = CompetitionSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isDraft()) {
                throw new AuthorizationException;
            }

            $submission->setRawAttributes($locked->getAttributes(), true);
            $this->checkComplete($submission);

            $submission->status = SubmissionStatus::Submitted;
            $submission->submitted_at = now();
            SubmissionReference::assign($submission);
        });

        GradeSubmission::dispatch($submission->id)->afterCommit();
        $user->notify(new SubmissionReceived($submission));

        return $submission;
    }

    /**
     * @throws ValidationException
     */
    private function checkComplete(CompetitionSubmission $submission): void
    {
        $errors = [];
        $competition = $submission->competition;

        if ($competition->categoryList() !== [] && blank($submission->category)) {
            $errors['category'] = __('validation.required', ['attribute' => __('competitions.entry.category')]);
        }

        if ($submission->region === null) {
            $errors['region'] = __('validation.required', ['attribute' => __('competitions.entry.region')]);
        }

        $videos = $submission->videos()->get()->keyBy('question_id');

        foreach ($competition->questions as $question) {
            $problem = $this->problem($submission, $question, $videos->get($question->id));

            if ($problem !== null) {
                $errors['answers.'.$question->id] = $problem;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function problem(CompetitionSubmission $submission, CompetitionQuestion $question, ?SubmissionVideo $video): ?string
    {
        if ($question->type !== QuestionType::Video) {
            return $question->is_required && $submission->answer($question) === null ? __('competitions.entry.required_answer') : null;
        }

        // مقطع قيد الرفع أو المعالجة يمنع الإرسال ولو كان اختيارياً، فلا يُرسل نصف مقطع
        return match (true) {
            $video !== null && in_array($video->status, [VideoStatus::Uploading, VideoStatus::Processing], true) => __('competitions.entry.video_pending'),
            $question->is_required && $video?->status !== VideoStatus::Ready => __('competitions.entry.required_video'),
            default => null,
        };
    }
}
