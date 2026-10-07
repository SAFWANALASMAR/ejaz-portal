<?php

namespace App\Console\Commands;

use App\Actions\Competitions\PurgeSubmissionPersonalData;
use App\Actions\Programs\PurgeProgramRegistrationPersonalData;
use App\Actions\Services\PurgeServiceRequestPersonalData;
use App\Actions\Surveys\PurgeSurveyResponsePersonalData;
use App\Models\CompetitionSubmission;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\Retention;
use Illuminate\Console\Command;

/**
 * يحذف بيانات الحسابات المحذوفة (المخفاة بطلب أصحابها) بعد مدة الاحتفاظ من اللوحة (App\Support\Retention)،
 * بإعداد واحد لكل الوحدات (القضية #6). وبلا مدة مضبوطة لا يحذف شيئاً.
 * - مقاطع المسابقات وإجاباتها: محسوبة من إغلاق المسابقة، فتبقى حتى انتهائها وفترة الاعتراض.
 * - الإجابات النصية في الاستبيانات، وإجابات الطلبات ومرفقاتها، وإجابات التسجيل في البرامج: من إخفاء
 *   بيانات الحساب.
 * وما عولج لا يُعاد، فلا يتكرر سجله كل يوم.
 */
class PurgeAnonymizedData extends Command
{
    protected $signature = 'ejaz:purge-anonymized-data';

    protected $description = 'حذف بيانات الحسابات المحذوفة بعد مدة الاحتفاظ';

    public function handle(
        PurgeSubmissionPersonalData $purgeSubmission,
        PurgeSurveyResponsePersonalData $purgeResponse,
        PurgeServiceRequestPersonalData $purgeRequest,
        PurgeProgramRegistrationPersonalData $purgeRegistration,
    ): int {
        $days = Retention::days();

        if ($days === null) {
            $this->info('مدة الاحتفاظ غير مضبوطة في اللوحة، فلم يُحذف شيء.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        // أصحاب الحسابات المخفاة قبل المدة
        $expired = User::query()->select('id')->where('anonymized_at', '<', $cutoff);
        $counts = ['submissions' => 0, 'responses' => 0, 'requests' => 0, 'registrations' => 0];

        CompetitionSubmission::query()
            ->withTrashed()
            ->whereHas('user', fn ($query) => $query->whereNotNull('anonymized_at'))
            // والمسابقة المحذوفة من اللوحة داخلة (العلاقة بـ withTrashed، وصريحة هنا)
            ->whereHas('competition', fn ($query) => $query->withTrashed()->where('closes_at', '<', $cutoff))
            // ما بقي فيه شيء يُحذف، فلا تُعاد المشاركة نفسها كل يوم
            ->where(fn ($query) => $query->whereNotNull('answers')->orWhereNotNull('region')->orWhereHas('videos'))
            ->lazyById()
            ->each(function (CompetitionSubmission $submission) use ($purgeSubmission, &$counts): void {
                $purgeSubmission->handle($submission);
                $counts['submissions']++;
            });

        // والمعالَجة تُفصل عن الحساب فلا تُعاد
        SurveyResponse::query()
            ->with('survey.questions')
            ->whereIn('user_id', $expired)
            ->lazyById()
            ->each(function (SurveyResponse $response) use ($purgeResponse, &$counts): void {
                $purgeResponse->handle($response);
                $counts['responses']++;
            });

        ServiceRequest::query()
            ->withTrashed()
            ->whereIn('user_id', $expired)
            ->where(fn ($query) => $query->whereNotNull('answers')->orWhereNotNull('files'))
            ->lazyById()
            ->each(function (ServiceRequest $request) use ($purgeRequest, &$counts): void {
                $purgeRequest->handle($request);
                $counts['requests']++;
            });

        ProgramRegistration::query()
            ->whereIn('user_id', $expired)
            ->whereNotNull('answers')
            ->lazyById()
            ->each(function (ProgramRegistration $registration) use ($purgeRegistration, &$counts): void {
                $purgeRegistration->handle($registration);
                $counts['registrations']++;
            });

        $this->info("مشاركات: {$counts['submissions']}، وإجابات استبيانات: {$counts['responses']}، وطلبات: {$counts['requests']}، وتسجيلات: {$counts['registrations']}.");

        return self::SUCCESS;
    }
}
