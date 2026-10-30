<?php

namespace App\Actions\Competitions;

use App\Enums\AttemptStatus;
use App\Jobs\SendExamReceipt;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionQuestion;
use App\Models\User;
use App\Notifications\AttemptReceived;
use App\Services\Messaging\MessagingSettings;
use App\Support\AccountSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * تسليم المحاولة وتصحيحها آلياً: من الطالب («تسليم الإجابات»)، أو آلياً عند انتهاء الوقت (المتصفح، أو أول
 * طلب بعد الموعد، أو ejaz:close-expired-attempts). يقفل الصف، والمسلَّمة تُعاد كما هي بلا أثر، فالتسليم
 * متكرر الأمان. والدرجة تُحفظ ولا تُعرض للطالب حتى تُنشر نتائج المرحلة. والمقالي يُصحَّح يدوياً.
 *
 * وبعد التسليم الأول وحده: بريد تأكيد الاستلام لمن في حسابه بريد (receipt_sent_at يمنع تكراره)، وواتساب خلف مفتاحه
 * (قرار صفوان والمدير التنفيذي، 30 أكتوبر 2026). وكلاهما في الطابور بعد حفظ التسليم.
 */
class SubmitAttempt
{
    /**
     * @param  User|null  $user  null للتسليم الآلي من المهمة المجدولة
     *
     * @throws AuthorizationException
     */
    public function handle(?User $user, CompetitionAttempt $attempt, bool $auto = false): CompetitionAttempt
    {
        if ($user !== null && $attempt->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $fresh = false;

        $locked = DB::transaction(function () use ($attempt, $auto, &$fresh): CompetitionAttempt {
            /** @var CompetitionAttempt $locked */
            $locked = CompetitionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isInProgress()) {
                return $locked;
            }

            $fresh = true;

            $late = now()->gt($locked->deadline_at);
            [$score, $max] = $this->grade($locked);

            $locked->status = AttemptStatus::Submitted;
            // «آلي» من الخادم لا من الطلب وحده: بعد الموعد، أو طلب العداد في نصف الدقيقة الأخيرة قبله
            $locked->auto_submitted = $late || ($auto && now()->gte($locked->deadline_at->copy()->subSeconds(30)));
            $locked->submitted_at = $late ? $locked->deadline_at : now();
            $locked->score = (float) $score;
            $locked->max_score = (float) $max;

            // بلا بريد في الحساب (التسجيل باسم المستخدم) لا إشعار ولا خطأ. وحين يكون التوثيق شرطاً فالموثّق وحده، لأن
            // البريد غير الموثق لا يتفرد فقد يكون لغيره. والموقوف أو المجهَّل لا شيء. وreceipt_sent_at سجل الإرسال
            $student = $locked->receipt_sent_at === null ? $locked->user : null;
            $notify = $student !== null && $student->is_active && filled($student->email)
                && ($student->hasVerifiedEmail() || ! AccountSettings::verificationEnabled());

            if ($notify) {
                $locked->receipt_sent_at = now();
            }

            $locked->save();

            if ($notify) {
                $student->notify(new AttemptReceived($locked));
            }

            return $locked;
        });

        if ($fresh && MessagingSettings::usageEnabled('whatsapp', 'exam_receipt')) {
            SendExamReceipt::dispatch($locked->submission_id, $locked->id)->onQueue('receipts')->afterCommit();
        }

        return $locked;
    }

    /**
     * الدرجة والدرجة العظمى: مجموع درجات الأسئلة الموضوعية التي طابقت إجابتها الخيار الصحيح، من البنك
     * مباشرة (الخيار الصحيح لا يُخزَّن في الذاكرة المؤقتة). والعظمى تشمل درجات المقالي.
     *
     * @return array{0: int, 1: int}
     */
    public function grade(CompetitionAttempt $attempt): array
    {
        $questions = CompetitionQuestion::query()->whereIn('id', $attempt->questionIds())->get(['id', 'type', 'correct_option', 'points']);
        $score = 0;
        $max = 0;

        foreach ($questions as $question) {
            $max += $question->points;

            if ($question->type->isChoice() && $question->correct_option !== null && $attempt->answer($question->id) === $question->correct_option) {
                $score += $question->points;
            }
        }

        return [$score, $max];
    }
}
