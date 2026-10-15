<?php

namespace App\Actions\Documents;

use App\Enums\ActivityEvent;
use App\Models\FormEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * حذف بيانات نموذج معبأ محفوظ في ملف من حُذف حسابه، بعد مدة الاحتفاظ (مهمة الإصلاحات الأمنية ٢):
 * الإجابات والمرفقات والتواقيع. ويبقى المعبأ نفسه برقمه المرجعي وإصداره وحالته وتواريخها، كبقية الطلبات.
 * يستدعيه ejaz:purge-anonymized-data وحده، ولا تراجع عنه.
 */
class PurgeFormEntryPersonalData
{
    public function handle(FormEntry $entry): void
    {
        $paths = self::paths($entry);

        // الملفات أولاً، خارج المعاملة: إن تعذر حذفها بقي المعبأ كما هو، فيعاد الحذف في الدورة التالية
        // (كـ PurgeServiceRequestPersonalData). والملف الغائب أصلاً نجاح
        if ($paths !== [] && ! Storage::disk(FormEntry::disk())->delete($paths)) {
            throw new RuntimeException("Cannot delete the files of form entry {$entry->id}.");
        }

        DB::transaction(function () use ($entry, $paths): void {
            /** @var FormEntry $locked */
            $locked = FormEntry::query()->withTrashed()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            // ملف تغيّر بين القراءة والقفل لم يُحذف: يُعاد في الدورة التالية، فلا يُفرَّغ مسار ملف باقٍ
            if (self::paths($locked) !== $paths) {
                throw new RuntimeException("The files of form entry {$locked->id} changed during the purge.");
            }

            // الإجابات والملفات والتواقيع لا تدخل في سجل النموذج (LogsActivity)
            $locked->forceFill(['answers' => null, 'files' => null, 'signatures' => null])->save();

            activity('documents')->performedOn($locked)
                ->event(ActivityEvent::PersonalDataPurged->value)
                ->withProperties(['form_template_version_id' => $locked->form_template_version_id, 'files' => count($paths)])
                ->log(ActivityEvent::PersonalDataPurged->value);
        });
    }

    /**
     * @return list<string> مسارات المرفقات والتواقيع مرتبة، فتُقارن نسختان منه
     */
    private static function paths(FormEntry $entry): array
    {
        $paths = [
            ...array_column((array) $entry->files, 'path'),
            ...array_column((array) $entry->signatures, 'path'),
        ];
        sort($paths);

        return $paths;
    }
}
