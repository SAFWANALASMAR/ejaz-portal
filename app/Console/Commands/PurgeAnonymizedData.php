<?php

namespace App\Console\Commands;

use App\Actions\Competitions\PurgeSubmissionPersonalData;
use App\Actions\Surveys\PurgeSurveyResponsePersonalData;
use App\Models\CompetitionSubmission;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\Retention;
use Illuminate\Console\Command;

/**
 * يحذف بيانات الحسابات المحذوفة (المخفاة بطلب أصحابها) بعد مدة الاحتفاظ من اللوحة (App\Support\Retention).
 * وبلا مدة مضبوطة لا يحذف شيئاً. ومقاطع المسابقات وإجاباتها محسوبة من إغلاق المسابقة، فتبقى حتى انتهائها
 * وفترة الاعتراض. والإجابات النصية في الاستبيانات محسوبة من إخفاء بيانات الحساب. ويتسع في فرع التكامل
 * للطلبات والتسجيلات (القضية #6).
 */
class PurgeAnonymizedData extends Command
{
    protected $signature = 'ejaz:purge-anonymized-data';

    protected $description = 'حذف بيانات الحسابات المحذوفة بعد مدة الاحتفاظ';

    public function handle(PurgeSubmissionPersonalData $purge, PurgeSurveyResponsePersonalData $purgeResponse): int
    {
        $days = Retention::days();

        if ($days === null) {
            $this->info('مدة الاحتفاظ غير مضبوطة في اللوحة، فلم يُحذف شيء.');

            return self::SUCCESS;
        }

        $purged = 0;

        CompetitionSubmission::query()
            ->withTrashed()
            ->whereHas('user', fn ($query) => $query->whereNotNull('anonymized_at'))
            // والمسابقة المحذوفة من اللوحة داخلة (العلاقة بـ withTrashed، وصريحة هنا)
            ->whereHas('competition', fn ($query) => $query->withTrashed()->where('closes_at', '<', now()->subDays($days)))
            // ما بقي فيه شيء يُحذف، فلا تُعاد المشاركة نفسها كل يوم
            ->where(fn ($query) => $query->whereNotNull('answers')->orWhereNotNull('region')->orWhereHas('videos'))
            ->lazyById()
            ->each(function (CompetitionSubmission $submission) use ($purge, &$purged): void {
                $purge->handle($submission);
                $purged++;
            });

        // إجابات الاستبيانات: المدة من إخفاء بيانات الحساب، والمعالَجة تُفصل عن الحساب فلا تُعاد
        $responses = 0;

        SurveyResponse::query()
            ->whereIn('user_id', User::query()->select('id')->where('anonymized_at', '<', now()->subDays($days)))
            ->lazyById()
            ->each(function (SurveyResponse $response) use ($purgeResponse, &$responses): void {
                $purgeResponse->handle($response);
                $responses++;
            });

        $this->info("مشاركات حُذفت بياناتها: {$purged}، وإجابات استبيانات: {$responses}.");

        return self::SUCCESS;
    }
}
