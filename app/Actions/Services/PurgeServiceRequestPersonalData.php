<?php

namespace App\Actions\Services;

use App\Enums\ActivityEvent;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * حذف بيانات طلب من حُذف حسابه بعد مدة الاحتفاظ (قرار صفوان على مراجعة #2): الإجابات والمرفقات
 * (السيرة الذاتية وغيرها)، وردود الموظفين وملاحظاتهم عليه. ويبقى الطلب نفسه برقمه وحالاته وتواريخها.
 * يستدعيه ejaz:purge-anonymized-data وحده، ولا تراجع عنه.
 */
class PurgeServiceRequestPersonalData
{
    public function handle(ServiceRequest $request): void
    {
        $paths = array_values(array_map(fn (array $file): string => $file['path'], (array) $request->files));

        // الملفات أولاً، خارج المعاملة: إن تعذر حذفها بقي الطلب كما هو، فيعاد الحذف في الدورة التالية
        // ولا يضيع مسار ملف باقٍ. وصاحب الحساب المخفى لا يغيّر مرفقاته بينهما. والقرص لا يرمي استثناءً
        // عند التعذر (throw: false) بل يعيد false، والملف الغائب أصلاً نجاح
        if ($paths !== [] && ! Storage::disk(ServiceRequest::disk())->delete($paths)) {
            throw new RuntimeException("Cannot delete the files of service request {$request->id}.");
        }

        DB::transaction(function () use ($request, $paths): void {
            /** @var ServiceRequest $locked */
            $locked = ServiceRequest::query()->withTrashed()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            // الإجابات والمرفقات لا تدخل في سجل النموذج (LogsActivity)، فالحفظ لا يكتبها في السجل
            $locked->forceFill(['answers' => null, 'files' => null])->save();

            // نص حر من الموظفين قد يحوي بيانات المتقدم، ويبقى من التغيير حالتاه ومنفّذه وتاريخه
            ServiceRequestEvent::query()->where('service_request_id', $locked->id)->update(['message' => null, 'internal_note' => null]);

            activity('requests')->performedOn($locked)
                ->event(ActivityEvent::PersonalDataPurged->value)
                ->withProperties(['opening_id' => $locked->opening_id, 'files' => count($paths)])
                ->log(ActivityEvent::PersonalDataPurged->value);
        });
    }
}
