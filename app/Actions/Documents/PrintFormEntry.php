<?php

namespace App\Actions\Documents;

use App\Enums\ActivityEvent;
use App\Enums\DocumentFieldType;
use App\Enums\FormEntryStatus;
use App\Models\FormEntry;
use App\Models\User;
use App\Support\SignatureImage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\File;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

/**
 * طباعة المعبأ PDF بترويسة الجمعية (requirements/05)، لمن يطّلع عليه، وتُسجَّل. بحقول إصداره لا الإصدار
 * الحالي، والتواقيع صورها، وعلى غير المعتمد علامة «مسودة». وإعدادات mPDF كشهادات الحضور: بلا جلب لأي
 * ملف أو رابط، وبلا إصدار المكتبة.
 */
class PrintFormEntry
{
    public function __construct(private LogDocumentAccess $log) {}

    /**
     * @return string محتوى الملف
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, FormEntry $entry): string
    {
        if (! $actor->can('view', $entry)) {
            throw new AuthorizationException;
        }

        $entry->loadMissing(['version.template', 'creator', 'approver']);

        $rows = array_map(function (array $field) use ($entry): array {
            $value = match ($field['type']) {
                DocumentFieldType::File => collect($entry->files ?? [])->firstWhere('key', $field['key'])['name'] ?? null,
                DocumentFieldType::Signature => null,
                default => $entry->answers[$field['key']] ?? null,
            };
            $signature = collect($entry->signatures ?? [])->firstWhere('key', $field['key']);

            return [
                'label' => $field['label'],
                'value' => $value,
                'signature' => $field['type'] === DocumentFieldType::Signature && $signature !== null ? SignatureImage::dataUrl(FormEntry::disk(), $signature['path']) : null,
            ];
        }, $entry->version->typedFields());

        $tempDir = storage_path('framework/mpdf');
        File::ensureDirectoryExists($tempDir);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'xbriyaz',
            'directionality' => 'rtl',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'whitelistStreamWrappers' => [],
            'exposeVersion' => false,
        ]);
        $pdf->SetTitle($entry->version->template->title);

        if ($entry->status === FormEntryStatus::Draft) {
            $pdf->SetWatermarkText(__('documents.print.draft'));
            $pdf->showWatermarkText = true;
        }

        $pdf->WriteHTML(view('documents.form-entry-pdf-style')->render(), HTMLParserMode::HEADER_CSS);
        // mPDF يستبدل نصوصاً مثل {PAGENO} في المحتوى عند الإخراج، ومنه إجابات المعبأ، فتُفصل الأقواس بمسافة
        // صفرية العرض لا تُرى (والأنماط قبلها منفصلة)
        $html = view('documents.form-entry-pdf', ['entry' => $entry, 'rows' => $rows])->render();
        $pdf->WriteHTML(str_replace('{', '{&#8203;', $html), HTMLParserMode::HTML_BODY);

        $this->log->handle($actor, $entry, ActivityEvent::FileDownloaded, ['file' => 'pdf']);

        return $pdf->Output('', 'S');
    }
}
