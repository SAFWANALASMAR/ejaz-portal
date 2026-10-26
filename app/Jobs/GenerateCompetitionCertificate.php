<?php

namespace App\Jobs;

use App\Enums\AttemptStatus;
use App\Models\CompetitionSubmission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

/**
 * شهادة المشارك في مسابقة الطلاب: PDF لكل من سلّم في مرحلة واحدة على الأقل، في القرص الخاص، تظهر في
 * «مشاركاتي» للتنزيل. في الطابور لأن التوليد يتجاوز ثانية (performance.md)، ومهام التسجيل الواحد لا تتداخل.
 * والقالب صورة الخلفية التي يرفعها المسؤول لكل مسابقة (قابل للتبديل من اللوحة)، وبلاها القالب المؤقت، ولا
 * تُصدر في الإنتاج بلا قالب (【ناقص: القالب المعتمد من الجمعية】). ولا يحمّل أي مورد خارجي.
 */
class GenerateCompetitionCertificate implements ShouldQueue
{
    use Queueable;

    public const DIRECTORY = 'competition-certificates';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public int $submissionId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('competition-certificate-'.$this->submissionId))->releaseAfter(10)];
    }

    public function handle(): void
    {
        $submission = CompetitionSubmission::query()->with(['competition', 'user'])->find($this->submissionId);

        if ($submission === null || ! self::eligible($submission) || ! self::issuable($submission)) {
            return;
        }

        $path = self::DIRECTORY.'/'.$submission->uuid.'.pdf';
        Storage::disk(self::disk())->put($path, self::render($submission));
        $submission->forceFill(['certificate_path' => $path, 'certificate_issued_at' => now()])->saveQuietly();
    }

    /**
     * مشارك: سلّم في مرحلة واحدة على الأقل، وحسابه قائم.
     */
    public static function eligible(CompetitionSubmission $submission): bool
    {
        return $submission->user->anonymized_at === null
            && $submission->attempts()->where('status', AttemptStatus::Submitted)->exists();
    }

    /**
     * القالب المؤقت للتجربة خارج الإنتاج، وفي الإنتاج بعد رفع قالب الجمعية وحده.
     */
    public static function issuable(CompetitionSubmission $submission): bool
    {
        return ! app()->isProduction() || filled($submission->competition->certificate_background);
    }

    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    /**
     * محتوى PDF من قالبه (resources/views/certificates/competition.blade.php)، وصورة الخلفية من القرص الخاص
     * بمتغير mPDF لا برابط، بلا جلب لأي ملف خارجي ولا إصدار المكتبة في بيانات الملف.
     */
    public static function render(CompetitionSubmission $submission): string
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
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
        ]);

        $background = $submission->competition->certificate_background;

        if (filled($background) && Storage::disk(self::disk())->exists((string) $background)) {
            $pdf->imageVars['background'] = (string) Storage::disk(self::disk())->get((string) $background);
        }

        $pdf->SetTitle(__('competitions.student.certificate_pdf.title'));
        $pdf->WriteHTML(view('certificates.competition-style')->render(), HTMLParserMode::HEADER_CSS);
        // mPDF يستبدل نصوصاً مثل {PAGENO} في المحتوى، ومنه اسم الطالب وعنوان المسابقة، فتُفصل الأقواس
        $html = view('certificates.competition', [
            'submission' => $submission,
            'hasBackground' => isset($pdf->imageVars['background']),
        ])->render();
        $pdf->WriteHTML(str_replace('{', '{&#8203;', $html), HTMLParserMode::HTML_BODY);

        return $pdf->Output('', 'S');
    }
}
