<?php

namespace App\Actions\Services;

use App\Enums\ActivityEvent;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * حذف بيانات طلب من حُذف حسابه بعد مدة الاحتفاظ (قرار صفوان على مراجعة #2): الإجابات والمرفقات
 * (السيرة الذاتية وغيرها). ويبقى الطلب نفسه برقمه وحالته وتواريخه. يستدعيه ejaz:purge-anonymized-data
 * وحده، ولا تراجع عنه.
 */
class PurgeServiceRequestPersonalData
{
    public function handle(ServiceRequest $request): void
    {
        /** @var list<string> $paths */
        $paths = DB::transaction(function () use ($request): array {
            /** @var ServiceRequest $locked */
            $locked = ServiceRequest::query()->withTrashed()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $paths = array_values(array_map(fn (array $file): string => $file['path'], (array) $locked->files));

            // الإجابات والمرفقات لا تدخل في سجل النموذج (LogsActivity)، فالحفظ لا يكتبها في السجل
            $locked->forceFill(['answers' => null, 'files' => null])->save();

            activity('requests')->performedOn($locked)
                ->event(ActivityEvent::PersonalDataPurged->value)
                ->withProperties(['opening_id' => $locked->opening_id, 'files' => count($paths)])
                ->log(ActivityEvent::PersonalDataPurged->value);

            return $paths;
        });

        // الملفات بعد اعتماد المعاملة، فلا يُحذف ملف بقي مساره
        Storage::disk(ServiceRequest::disk())->delete($paths);
    }
}
