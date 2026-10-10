<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Documents\LogDocumentAccess;
use App\Actions\Documents\PrintFormEntry;
use App\Enums\ActivityEvent;
use App\Filament\Resources\FormEntries\FormEntryResource;
use App\Http\Controllers\Controller;
use App\Models\FormEntry;
use App\Models\User;
use App\Support\FormEntryLayout;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * المعبأ كورقة وورد في المتصفح وPDF، لمن يطّلع عليه في اللوحة (FormEntryPolicy::view). والاطلاع والطباعة
 * يُسجَّلان (requirements/05).
 */
class FormEntryPrintController extends Controller
{
    public function show(Request $request, FormEntry $entry, LogDocumentAccess $log): View
    {
        abort_unless($request->user()?->can('view', $entry), 403);

        /** @var User $actor */
        $actor = $request->user();
        $log->handle($actor, $entry, ActivityEvent::Viewed, ['view' => 'print']);

        return view('documents.form-entry-print', [
            ...FormEntryLayout::viewData($entry),
            'pdfUrl' => route('documents.entries.pdf', $entry),
            'backUrl' => FormEntryResource::getUrl('view', ['record' => $entry]),
        ]);
    }

    public function pdf(Request $request, FormEntry $entry, PrintFormEntry $print): Response
    {
        abort_unless($request->user()?->can('view', $entry), 403);

        /** @var User $actor */
        $actor = $request->user();

        return self::download($print->handle($actor, $entry), $entry);
    }

    public static function download(string $pdf, FormEntry $entry): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$entry->reference.'.pdf"',
        ]);
    }
}
