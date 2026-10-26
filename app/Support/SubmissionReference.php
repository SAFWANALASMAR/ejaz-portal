<?php

namespace App\Support;

use App\Models\CompetitionSubmission;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * الرقم المرجعي للمشاركة المرسلة: عشرة حروف بالمولّد الآمن، ويُعاد توليده في التصادم النادر (القيد
 * الفريد). ويحفظ المشاركة معه. يستعمله إرسال النموذج (SubmitSubmission) والاختبار (SubmitExam).
 */
final class SubmissionReference
{
    /**
     * حروف الرقم المرجعي: بلا ما يلتبس بعضه ببعض (O و0، وI و1).
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function assign(CompetitionSubmission $submission): void
    {
        for ($attempt = 1; ; $attempt++) {
            $submission->reference = self::generate();

            try {
                DB::transaction(fn () => $submission->save());

                return;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 5) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * رقم مرجعي جديد بلا حفظ: للمشاركة هنا، ولمحاولة مسابقة الطلاب (StartAttempt).
     */
    public static function generate(): string
    {
        $reference = '';

        for ($i = 0; $i < 10; $i++) {
            $reference .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $reference;
    }
}
