<?php

namespace App\Http\Controllers\Account;

use App\Actions\Activity\LogAccess;
use App\Actions\Documents\PrintFormEntry;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Documents\FormEntryPrintController;
use App\Models\FormEntry;
use App\Models\User;
use App\Support\FormEntryLayout;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * «نماذجي» في لوحة المستخدم: المعتمد من نماذج المشاركين المحفوظة في ملف صاحب الحساب
 * (FormEntryPolicy::viewAsPerson)، يعرضه كورقة ويطبعه. ولا مسودات، ولا نماذج الموظفين، ولا معبأ غيره.
 */
class FormEntriesController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $entries = FormEntry::query()
            ->visibleToPerson($user)
            ->with('version.template')
            ->latest('approved_at')
            ->paginate(20);

        abort_if($entries->currentPage() > max(1, $entries->lastPage()), 404);

        return view('account.forms', ['entries' => $entries]);
    }

    public function show(Request $request, FormEntry $entry, LogAccess $log): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('viewAsPerson', $entry), 403);

        $log->handle($user, $entry, 'documents', ActivityEvent::Viewed, ['view' => 'account']);

        return view('documents.form-entry-print', [
            ...FormEntryLayout::viewData($entry),
            'pdfUrl' => route('account.forms.pdf', $entry),
            'backUrl' => route('account.forms.index'),
        ]);
    }

    public function pdf(Request $request, FormEntry $entry, PrintFormEntry $print): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('viewAsPerson', $entry), 403);

        return FormEntryPrintController::download($print->handle($user, $entry), $entry);
    }
}
