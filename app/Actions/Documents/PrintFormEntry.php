<?php

namespace App\Actions\Documents;

use App\Actions\Activity\LogAccess;
use App\Enums\ActivityEvent;
use App\Enums\FormEntryStatus;
use App\Models\FormEntry;
use App\Models\User;
use App\Support\FormEntryLayout;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\File;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

/**
 * طباعة المعبأ PDF كورقة وورد على A4 (requirements/05، و12 البند 10)، لمن يطّلع عليه، وتُسجَّل: هوامش،
 * وترويسة الجمعية في كل صفحة، وتذييل برقم الصفحة من مجموعها، والمتن بكتل إصداره لا الإصدار الحالي
 * (FormEntryLayout)، وعلى غير المعتمد علامة «مسودة». وإعدادات mPDF كشهادات الحضور: بلا جلب لأي رابط،
 * وبلا إصدار المكتبة. والخط xbriyaz: خط Zain يُبنى WOFF وmPDF يقرأ TTF وحده (decisions.md).
 */
class PrintFormEntry
{
    public function __construct(private LogAccess $log) {}

    /**
     * @return string محتوى الملف
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, FormEntry $entry): string
    {
        // من يطّلع على المعبأ في اللوحة، أو صاحبه المشارك على المعتمد (FormEntryPolicy::viewAsPerson)
        if (! $actor->can('view', $entry) && ! $actor->can('viewAsPerson', $entry)) {
            throw new AuthorizationException;
        }

        $entry->loadMissing(['version.template', 'creator', 'approver', 'person']);

        $tempDir = storage_path('framework/mpdf');
        File::ensureDirectoryExists($tempDir);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'xbriyaz',
            'directionality' => 'rtl',
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_top' => 40,
            'margin_bottom' => 24,
            'margin_header' => 10,
            'margin_footer' => 10,
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'whitelistStreamWrappers' => [],
            'exposeVersion' => false,
            // الترويسة تطول إن انكسرت بيانات التواصل على سطرين، فيتسع الهامش العلوي لها
            'setAutoTopMargin' => 'stretch',
        ]);
        $pdf->SetTitle($entry->version->template->title);

        if ($entry->status === FormEntryStatus::Draft) {
            $pdf->SetWatermarkText(__('documents.print.draft'));
            $pdf->showWatermarkText = true;
        }

        $pdf->WriteHTML(view('documents.form-entry-pdf-style')->render(), HTMLParserMode::HEADER_CSS);

        // mPDF يستبدل نصوصاً مثل {PAGENO} في المحتوى عند الإخراج، ومنه الإجابات والإعدادات، فتُفصل الأقواس
        // بمسافة صفرية العرض لا تُرى. ورقم الصفحة في التذييل وحده يُترك له
        $header = view('documents.partials.form-letterhead', ['letterhead' => FormEntryLayout::letterhead(), 'logo' => FormEntryLayout::logoPath()])->render();
        $pdf->SetHTMLHeader(self::literal($header));

        $page = e(__('documents.print.page', ['page' => '[[page]]', 'pages' => '[[pages]]']));
        $pdf->SetHTMLFooter('<table class="page-footer"><tr><td>'.self::literal(e($entry->version->template->title).' — '.e($entry->reference)).'</td>'
            .'<td class="page-number">'.str_replace(['[[page]]', '[[pages]]'], ['{PAGENO}', '{nbpg}'], self::literal($page)).'</td></tr></table>');

        $html = view('documents.form-entry-pdf', ['entry' => $entry, 'blocks' => FormEntryLayout::blocks($entry)])->render();
        $pdf->WriteHTML(self::literal($html), HTMLParserMode::HTML_BODY);

        $this->log->handle($actor, $entry, 'documents', ActivityEvent::FileDownloaded, ['file' => 'pdf']);

        return $pdf->Output('', 'S');
    }

    private static function literal(string $html): string
    {
        return str_replace('{', '{&#8203;', $html);
    }
}
