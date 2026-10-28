<?php

namespace App\Console\Commands;

use App\Enums\RegistrationStatus;
use App\Jobs\SyncAttendanceCertificate;
use App\Models\ProgramRegistration;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * الشهادات الموقوفة قبل قالب الجمعية: الحضور المسجَّل في الإنتاج قبل PROGRAM_CERTIFICATE_TEMPLATE_READY=true لا
 * يعيد إرسال مهمته شيء، فيُشغَّل هذا الأمر مرة بعد تفعيل القالب (مراجعة ما بعد v0.12.0، ملاحظة مهمة ١). يرسل مهمة
 * لكل مسجل مؤكد حضر بلا شهادة، على دفعات، والمهمة تعيد فحص الأهلية عند تنفيذها.
 */
class IssuePendingCertificates extends Command
{
    protected $signature = 'programs:issue-pending-certificates';

    protected $description = 'Queue the attendance certificates held back before the association template was set';

    public function handle(): int
    {
        if (! SyncAttendanceCertificate::issuable()) {
            $this->error('PROGRAM_CERTIFICATE_TEMPLATE_READY is not true: no certificate can be issued yet.');

            return self::FAILURE;
        }

        $queued = 0;

        ProgramRegistration::query()
            ->where('status', RegistrationStatus::Confirmed->value)
            ->whereNotNull('attended_at')
            ->whereNull('certificate_path')
            ->whereHas('user', fn (Builder $user) => $user->whereNull('anonymized_at'))
            ->chunkById(500, function ($registrations) use (&$queued): void {
                foreach ($registrations as $registration) {
                    SyncAttendanceCertificate::dispatch($registration);
                    $queued++;
                }
            });

        $this->info((string) $queued);

        return self::SUCCESS;
    }
}
