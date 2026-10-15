<?php

namespace App\Console\Commands;

use App\Actions\Competitions\PurgeSubmissionPersonalData;
use App\Actions\Documents\PurgeFormEntryPersonalData;
use App\Actions\Programs\PurgeProgramRegistrationPersonalData;
use App\Actions\Services\PurgeServiceRequestPersonalData;
use App\Actions\Surveys\PurgeSurveyResponsePersonalData;
use App\Enums\ActivityEvent;
use App\Models\CompetitionSubmission;
use App\Models\FormEntry;
use App\Models\Inquiry;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\Retention;
use Illuminate\Console\Command;
use Throwable;

/**
 * يحذف بيانات الحسابات المحذوفة (المخفاة بطلب أصحابها) بعد مدة الاحتفاظ من اللوحة (App\Support\Retention)،
 * بإعداد واحد لكل الوحدات (القضية #6). وبلا مدة مضبوطة لا يحذف شيئاً.
 * - مقاطع المسابقات وإجاباتها: محسوبة من إغلاق المسابقة، فتبقى حتى انتهائها وفترة الاعتراض.
 * - الإجابات النصية في الاستبيانات، وإجابات الطلبات ومرفقاتها وردود الموظفين وملاحظاتهم الداخلية عليها،
 *   وإجابات التسجيل في البرامج، وإجابات النماذج المعبأة في ملفه ومرفقاتها وتواقيعها: من إخفاء بيانات الحساب.
 * ونص رسائل «تواصل معنا» يُحذف عند الإخفاء نفسه (AnonymizeUser) بلا مدة، فيُحذف هنا ولو لم تُضبط المدة
 * لما بقي فيه نص: رسائل من أُخفي قبل قرار الحذف (14 أكتوبر 2026)، أو أعادتها استعادة من نسخة.
 * وما عولج لا يُعاد، فلا يتكرر سجله كل يوم. وسجل يتعذر حذفه يبقى كما هو للدورة التالية ولا يوقف الباقي.
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
        PurgeFormEntryPersonalData $purgeFormEntry,
    ): int {
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

        $this->info('رسائل تواصل حُذف نصها: '.$this->clearInquiryText($attempt).'.');

        $days = Retention::days();

        if ($days === null) {
            $this->info('مدة الاحتفاظ غير مضبوطة في اللوحة، فلم يُحذف شيء مما يتبعها.');

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        // أصحاب الحسابات المخفاة قبل المدة
        $expired = User::query()->select('id')->where('anonymized_at', '<', $cutoff);

        CompetitionSubmission::query()
            ->withTrashed()
            ->whereHas('user', fn ($query) => $query->whereNotNull('anonymized_at'))
            // والمسابقة المحذوفة من اللوحة داخلة (العلاقة بـ withTrashed، وصريحة هنا)
            ->whereHas('competition', fn ($query) => $query->withTrashed()->where('closes_at', '<', $cutoff))
            // ما بقي فيه شيء يُحذف، فلا تُعاد المشاركة نفسها كل يوم
            // (ومشاركة الاختبار بلا منطقة، ومسودة الأعمال بلا إجابات، فيُنظر في بيانات التسجيل والأعمال أيضاً)
            ->where(fn ($query) => $query->whereNotNull('answers')->orWhereNotNull('region')->orWhereHas('videos')->orWhereHas('works')
                ->orWhereNotNull('full_name')->orWhereNotNull('contact_email')->orWhereNotNull('national_id_hash'))
            ->lazyById()
            ->each(fn (CompetitionSubmission $submission) => $attempt(fn () => $purgeSubmission->handle($submission)));

        // والمعالَجة تُفصل عن الحساب فلا تُعاد
        SurveyResponse::query()
            ->with('survey.questions')
            ->whereIn('user_id', $expired)
            ->lazyById()
            ->each(fn (SurveyResponse $response) => $attempt(fn () => $purgeResponse->handle($response)));

        ServiceRequest::query()
            ->withTrashed()
            ->whereIn('user_id', $expired)
            ->where(fn ($query) => $query->whereNotNull('answers')->orWhereNotNull('files')
                ->orWhereHas('events', fn ($query) => $query->whereNotNull('message')->orWhereNotNull('internal_note')))
            ->lazyById()
            ->each(fn (ServiceRequest $request) => $attempt(fn () => $purgeRequest->handle($request)));

        ProgramRegistration::query()
            ->whereIn('user_id', $expired)
            ->whereNotNull('answers')
            ->lazyById()
            ->each(fn (ProgramRegistration $registration) => $attempt(fn () => $purgeRegistration->handle($registration)));

        // النماذج المعبأة المحفوظة في ملفه، والمحذوفة من اللوحة داخلة
        FormEntry::query()
            ->withTrashed()
            ->whereIn('person_id', $expired)
            ->where(fn ($query) => $query->whereNotNull('answers')->orWhereNotNull('files')->orWhereNotNull('signatures'))
            ->lazyById()
            ->each(fn (FormEntry $entry) => $attempt(fn () => $purgeFormEntry->handle($entry)));

        $summary = "سجلات حُذفت بياناتها: {$purged}، وتعذر: {$failed}.";

        if ($failed > 0) {
            $this->warn($summary);

            return self::FAILURE;
        }

        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * نص رسائل الحسابات المخفاة: من حسابه، أو من بريده الموثّق (وبريدها بعد الإخفاء بريد الحساب المخفى).
     * ويبقى الرقم والنوع والحالة والتاريخ، ولكل رسالة سجل. والنص الحر كله، ومنه الملاحظة الداخلية.
     *
     * @param  callable(callable): void  $attempt  الرسالة التي يتعذر حذف نصها لا توقف بقية التنظيف
     */
    private function clearInquiryText(callable $attempt): int
    {
        $cleared = 0;

        Inquiry::query()
            ->withTrashed()
            ->where(function ($query): void {
                foreach (Inquiry::FREE_TEXT as $column) {
                    $query->orWhereNotNull($column);
                }
            })
            ->where(fn ($query) => $query
                ->whereIn('user_id', User::query()->select('id')->whereNotNull('anonymized_at'))
                ->orWhereIn('email', User::query()->select('email')->whereNotNull('anonymized_at')))
            ->lazyById()
            ->each(function (Inquiry $inquiry) use ($attempt, &$cleared): void {
                $attempt(function () use ($inquiry, &$cleared): void {
                    $inquiry->forceFill(array_fill_keys(Inquiry::FREE_TEXT, null))->save();

                    activity('requests')->performedOn($inquiry)
                        ->event(ActivityEvent::PersonalDataPurged->value)
                        ->log(ActivityEvent::PersonalDataPurged->value);

                    $cleared++;
                });
            });

        return $cleared;
    }
}
