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
use Filament\Facades\Filament;
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
        self::authorizeStaff($request, $entry);

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
        self::authorizeStaff($request, $entry);

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

    /**
     * من يدخل اللوحة نفسها ويطّلع على المعبأ: المسار خارج /admin، فلا يُمنح دور خارجها اطلاعاً بلا شروطها.
     */
    private static function authorizeStaff(Request $request, FormEntry $entry): void
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->canAccessPanel(Filament::getPanel('admin')) && $user->can('view', $entry), 403);
    }
}
