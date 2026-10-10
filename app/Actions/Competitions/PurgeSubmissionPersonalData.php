<?php

namespace App\Actions\Competitions;

use App\Contracts\VideoUploads;
use App\Enums\ActivityEvent;
use App\Models\CompetitionSubmission;
use App\Models\SubmissionVideo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * حذف بيانات مشاركة من حُذف حسابه بعد مدة الاحتفاظ (قرار صفوان، مراجعة #1): مقاطعها (الملفات وبقايا
 * الرفع والسجلات)، وإجاباتها، ومنطقتها. ويبقى الرقم المرجعي والحالة والفئة والدرجات وتحكيمها لسلامة
 * النتائج والسحب. يستدعيه ejaz:purge-anonymized-data وحده، ولا تراجع عنه.
 */
class PurgeSubmissionPersonalData
{
    public function __construct(private VideoUploads $uploads) {}

    public function handle(CompetitionSubmission $submission): void
    {
        $videos = SubmissionVideo::query()->where('submission_id', $submission->id)->get();

        // الملفات أولاً، خارج المعاملة: إن تعذر حذفها بقيت المشاركة وسجلات مقاطعها كما هي، فيعاد الحذف
        // في الدورة التالية ولا يضيع مسار ملف باقٍ. وصاحب الحساب المخفى لا يرفع مقطعاً جديداً بينهما
        foreach ($videos as $video) {
            $this->uploads->discard($video);

            // القرص لا يرمي استثناءً عند التعذر (throw: false) بل يعيد false، والملف الغائب أصلاً نجاح
            if (filled($video->path) && ! Storage::disk(SubmissionVideo::disk())->delete((string) $video->path)) {
                throw new RuntimeException("Cannot delete the video of submission {$submission->id}.");
            }
        }

        DB::transaction(function () use ($submission, $videos): void {
            /** @var CompetitionSubmission $locked */
            $locked = CompetitionSubmission::query()->withTrashed()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

            // مهمة معالجة أنهت مقطعاً بين القراءة والقفل فظهر مسار لم يُحذف ملفه: يُعاد في الدورة التالية، فلا
            // يُحذف صف يشير إلى ملف باقٍ. وبعد القفل تنتظر المهمة، ثم لا تجد الصف فتحذف ملفها
            $current = SubmissionVideo::query()->where('submission_id', $locked->id)->lockForUpdate()->pluck('path', 'id')->sortKeys()->all();

            if ($current !== $videos->pluck('path', 'id')->sortKeys()->all()) {
                throw new RuntimeException("The videos of submission {$locked->id} changed during the purge.");
            }

            SubmissionVideo::query()->whereKey(array_keys($current))->delete();

            // الإجابات والمنطقة لا تدخل في سجل النموذج (LogsActivity)، فالحفظ لا يكتبها في السجل
            $locked->forceFill(['answers' => null, 'region' => null])->save();

            activity('competitions')->performedOn($locked)
                ->event(ActivityEvent::PersonalDataPurged->value)
                ->withProperties(['competition_id' => $locked->competition_id, 'videos' => $videos->count()])
                ->log(ActivityEvent::PersonalDataPurged->value);
        });
    }
}
