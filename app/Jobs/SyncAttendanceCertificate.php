<?php

namespace App\Jobs;

use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

/**
 * شهادة الحضور تتبع حالة الحضور: تُولَّد PDF لمسجل مؤكد سُجّل حضوره، وتُحذف إن أُلغي حضوره.
 * في الطابور لأن التوليد يتجاوز ثانية (performance.md). والقالب مؤقت حتى يصل قالب الجمعية.
 */
class SyncAttendanceCertificate implements ShouldQueue
{
    use Queueable;

    public const DIRECTORY = 'certificates';

    public function __construct(public ProgramRegistration $registration) {}

    public function handle(): void
    {
        $registration = $this->registration->fresh(['program', 'user']);

        if ($registration === null) {
            return;
        }

        $disk = Storage::disk(self::disk());
        $eligible = $registration->status === RegistrationStatus::Confirmed && $registration->attended_at !== null;

        if (! $eligible) {
            if ($registration->certificate_path !== null) {
                $disk->delete($registration->certificate_path);
                $registration->forceFill(['certificate_path' => null, 'certificate_issued_at' => null])->saveQuietly();
            }

            return;
        }

        $path = self::DIRECTORY.'/'.$registration->uuid.'.pdf';
        $disk->put($path, self::render($registration));
        $registration->forceFill(['certificate_path' => $path, 'certificate_issued_at' => now()])->saveQuietly();
    }

    /**
     * محتوى PDF للشهادة من قالبها (resources/views/certificates/attendance.blade.php).
     */
    public static function render(ProgramRegistration $registration): string
    {
        $tempDir = storage_path('framework/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'default_font' => 'xbriyaz',
            'directionality' => 'rtl',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $pdf->SetTitle(__('programs.certificate.title'));
        $pdf->WriteHTML(view('certificates.attendance', ['registration' => $registration])->render());

        return $pdf->Output('', 'S');
    }

    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }
}
