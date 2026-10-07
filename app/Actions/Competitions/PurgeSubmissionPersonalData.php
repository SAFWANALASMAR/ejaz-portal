<?php

namespace App\Actions\Competitions;

use App\Contracts\VideoUploads;
use App\Enums\ActivityEvent;
use App\Models\CompetitionSubmission;
use App\Models\SubmissionVideo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        /** @var Collection<int, SubmissionVideo> $videos */
        $videos = DB::transaction(function () use ($submission): Collection {
            /** @var CompetitionSubmission $locked */
            $locked = CompetitionSubmission::query()->withTrashed()->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $videos = $locked->videos()->lockForUpdate()->get();

            SubmissionVideo::query()->whereKey($videos->modelKeys())->delete();

            // الإجابات والمنطقة لا تدخل في سجل النموذج (LogsActivity)، فالحفظ لا يكتبها في السجل
            $locked->forceFill(['answers' => null, 'region' => null])->save();

            activity('competitions')->performedOn($locked)
                ->event(ActivityEvent::PersonalDataPurged->value)
                ->withProperties(['competition_id' => $locked->competition_id, 'videos' => $videos->count()])
                ->log(ActivityEvent::PersonalDataPurged->value);

            return $videos;
        });

        // الملفات بعد اعتماد المعاملة، فلا يُحذف ملف بقي سجله إن فشلت
        foreach ($videos as $video) {
            $this->uploads->discard($video);

            if (filled($video->path)) {
                Storage::disk(SubmissionVideo::disk())->delete((string) $video->path);
            }
        }
    }
}
