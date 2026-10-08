<?php

namespace App\Console\Commands;

use App\Actions\Competitions\PurgeSubmissionPersonalData;
use App\Models\CompetitionSubmission;
use App\Support\Retention;
use Illuminate\Console\Command;
use Throwable;

/**
 * يحذف بيانات الحسابات المحذوفة (المخفاة بطلب أصحابها) بعد مدة الاحتفاظ من اللوحة (App\Support\Retention).
 * وبلا مدة مضبوطة لا يحذف شيئاً. ومقاطع المسابقات وإجاباتها محسوبة من إغلاق المسابقة، فتبقى حتى انتهائها
 * وفترة الاعتراض. ويتسع في فرع التكامل للطلبات والتسجيلات والاستبيانات (القضية #6).
 */
class PurgeAnonymizedData extends Command
{
    protected $signature = 'ejaz:purge-anonymized-data';

    protected $description = 'حذف بيانات الحسابات المحذوفة بعد مدة الاحتفاظ';

    public function handle(PurgeSubmissionPersonalData $purge): int
    {
        $days = Retention::days();

        if ($days === null) {
            $this->info('مدة الاحتفاظ غير مضبوطة في اللوحة، فلم يُحذف شيء.');

            return self::SUCCESS;
        }

        $purged = 0;
        $failed = 0;

        // سجل يتعذر حذفه (تعطل التخزين مثلاً) يُبلَّغ عنه ويبقى كما هو للدورة التالية، ولا يوقف الباقي
        $attempt = function (callable $purge) use (&$purged, &$failed): void {
            try {
                $purge();
                $purged++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        };

        CompetitionSubmission::query()
            ->withTrashed()
            ->whereHas('user', fn ($query) => $query->whereNotNull('anonymized_at'))
            // والمسابقة المحذوفة من اللوحة داخلة (العلاقة بـ withTrashed، وصريحة هنا)
            ->whereHas('competition', fn ($query) => $query->withTrashed()->where('closes_at', '<', now()->subDays($days)))
            // ما بقي فيه شيء يُحذف، فلا تُعاد المشاركة نفسها كل يوم
            ->where(fn ($query) => $query->whereNotNull('answers')->orWhereNotNull('region')->orWhereHas('videos'))
            ->lazyById()
            ->each(fn (CompetitionSubmission $submission) => $attempt(fn () => $purge->handle($submission)));

        $summary = "مشاركات حُذفت بياناتها: {$purged}، وتعذر: {$failed}.";

        if ($failed > 0) {
            $this->warn($summary);

            return self::FAILURE;
        }

        $this->info($summary);

        return self::SUCCESS;
    }
}
