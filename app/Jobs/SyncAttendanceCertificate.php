<?php

namespace App\Jobs;

use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use RuntimeException;

/**
 * شهادة الحضور تتبع حالة الحضور: تُولَّد PDF لمسجل مؤكد سُجّل حضوره، وتُحذف إن أُلغي حضوره.
 * في الطابور لأن التوليد يتجاوز ثانية (performance.md). ومهام التسجيل الواحد لا تتداخل، وكل
 * مهمة تقرأ الحالة عند تنفيذها، فتبقى آخر حالة هي الصحيحة ولو تتابع التسجيل والإلغاء.
 * والقالب مؤقت حتى يصل قالب الجمعية، ولا يحمّل أي مورد خارجي.
 */
class SyncAttendanceCertificate implements ShouldQueue
{
    use Queueable;

    public const DIRECTORY = 'certificates';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public ProgramRegistration $registration) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('certificate-'.$this->registration->getKey()))->releaseAfter(10)];
    }

    public function handle(): void
    {
        $registration = $this->registration->fresh(['program', 'user']);

        if ($registration === null) {
            return;
        }

        // والإتمام في برامج المكتبة كالحضور: شهادة إتمام بالقالب نفسه (docs/decisions/2026-11-02-reading-program.md)
        $eligible = $registration->status === RegistrationStatus::Confirmed
            && ($registration->attended_at !== null || $registration->completed_at !== null)
            && $registration->user->anonymized_at === null;

        if (! $eligible) {
            self::remove($registration);

            return;
        }

        // لا شهادة ناقصة في الإنتاج: تُصدر بعد ضبط قالب الجمعية، والمسؤول يرى السبب في جدول المسجلين
        if (! self::issuable()) {
            return;
        }

        $path = self::DIRECTORY.'/'.$registration->uuid.'.pdf';
        Storage::disk(self::disk())->put($path, self::render($registration));
        $registration->forceFill(['certificate_path' => $path, 'certificate_issued_at' => now()])->saveQuietly();
    }

    /**
     * حذف ملف الشهادة ومساره (إلغاء الحضور، أو إخفاء بيانات الحساب).
     */
    public static function remove(ProgramRegistration $registration): void
    {
        if ($registration->certificate_path === null) {
            return;
        }

        // القرص لا يرمي استثناءً عند التعذر (throw: false) بل يعيد false. فيبقى المسار لإعادة المحاولة، ولا
        // يبقى ملف بالاسم بلا مرجع إليه. والملف الغائب أصلاً نجاح
        if (! Storage::disk(self::disk())->delete($registration->certificate_path)) {
            throw new RuntimeException("Cannot delete the certificate of registration {$registration->id}.");
        }

        $registration->forceFill(['certificate_path' => null, 'certificate_issued_at' => null])->saveQuietly();
    }

    /**
     * القالب المؤقت للتجربة خارج الإنتاج، وفي الإنتاج بعد ضبط قالب الجمعية وحده (config/programs.php).
     */
    public static function issuable(): bool
    {
        return ! app()->isProduction() || config('programs.certificate_template_ready') === true;
    }

    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    /**
     * محتوى PDF للشهادة من قالبها (resources/views/certificates/attendance.blade.php). بلا جلب
     * لأي ملف أو رابط، وبلا إصدار المكتبة في بيانات الملف.
     */
    private static function render(ProgramRegistration $registration): string
    {
        $tempDir = storage_path('framework/mpdf');
        File::ensureDirectoryExists($tempDir);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'default_font' => 'xbriyaz',
            'directionality' => 'rtl',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'whitelistStreamWrappers' => [],
            'exposeVersion' => false,
        ]);
        $pdf->SetTitle($registration->completed_at !== null ? __('programs.certificate.completion_title') : __('programs.certificate.title'));
        $pdf->WriteHTML(view('certificates.attendance-style')->render(), HTMLParserMode::HEADER_CSS);
        // mPDF يستبدل نصوصاً مثل {PAGENO} و{DATE} في المحتوى عند الإخراج، ومنه اسم المسجل وعنوان
        // البرنامج، فتُفصل الأقواس بمسافة صفرية العرض لا تُرى
        $html = view('certificates.attendance', ['registration' => $registration])->render();
        $pdf->WriteHTML(str_replace('{', '{&#8203;', $html), HTMLParserMode::HTML_BODY);

        return $pdf->Output('', 'S');
    }
}
