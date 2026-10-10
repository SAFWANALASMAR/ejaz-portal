<?php

namespace App\Actions\Competitions;

use App\Enums\EducationStage;
use App\Enums\ExamStage;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Rules\SaudiNationalId;
use App\Support\Digits;
use App\Support\ExamLock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * المرحلة الأولى من الاختبار: التسجيل والتحقق من البيانات. الاسم الكامل، والبريد اختياري، والمرحلة
 * التعليمية، والتعهد بصحة البيانات. والجوال الموثّق من الحساب (شرط بدء المشاركة). ورقم الهوية أو
 * الإقامة خلف مفتاح معطّل حتى قرار صفوان: مشفّر، وعدم تكراره في المسابقة ببصمته.
 */
class RegisterForExam
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, CompetitionSubmission $submission, array $data): CompetitionSubmission
    {
        $collectId = (bool) config('competitions.exam.national_id');

        if ($collectId && is_string($data['national_id'] ?? null)) {
            $data['national_id'] = Digits::latin(trim($data['national_id']));
        }

        $validated = Validator::make($data, [
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'national_id' => $collectId ? ['required', 'string', new SaudiNationalId] : ['prohibited'],
            'contact_email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'education_stage' => ['required', Rule::enum(EducationStage::class)],
            'pledge' => ['accepted'],
        ], attributes: __('competitions.exam.attributes'))->validate();

        try {
            return DB::transaction(function () use ($user, $submission, $validated, $collectId): CompetitionSubmission {
                $locked = ExamLock::stage($user, $submission, ExamStage::Registration);

                $locked->full_name = trim((string) $validated['full_name']);
                $locked->contact_email = filled($validated['contact_email'] ?? null) ? trim((string) $validated['contact_email']) : null;
                $locked->education_stage = EducationStage::from((string) $validated['education_stage']);

                if ($collectId) {
                    $locked->national_id = (string) $validated['national_id'];
                    $locked->national_id_hash = self::hash((string) $validated['national_id']);
                }

                $locked->registered_at = now();
                $locked->save();

                return $locked;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['national_id' => __('competitions.exam.national_id_taken')]);
        }
    }

    /**
     * بصمة الرقم بمفتاح مستقل (لا مفتاح التشفير، فلا يُبطل تدويرُه منع التكرار): تُقارن لمنع التكرار، ولا
     * يُستخرج منها الرقم بلا المفتاح. وبلا مفتاح لا يُجمع الرقم أصلاً.
     */
    public static function hash(string $nationalId): string
    {
        $key = (string) config('competitions.exam.national_id_key');

        if ($key === '') {
            throw new RuntimeException('COMPETITION_NATIONAL_ID_HMAC_KEY must be set before collecting national IDs.');
        }

        return hash_hmac('sha256', $nationalId, $key);
    }
}
