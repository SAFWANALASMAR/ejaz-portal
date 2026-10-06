<?php

namespace App\Http\Controllers\Account;

use App\Actions\Accounts\ExportUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\RequestAccountDeletionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * حقوق صاحب البيانات: تنزيل نسخة من بياناته، وطلب حذف حسابه.
 */
class DataRightsController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.data', ['user' => $request->user()]);
    }

    public function export(Request $request, ExportUserData $action): Response
    {
        /** @var User $user */
        $user = $request->user();

        activity('security')->causedBy($user)->performedOn($user)->event('data_exported')->log('data_exported');

        return response()->json(
            $action->handle($user),
            200,
            ['Content-Disposition' => 'attachment; filename="ejaz-my-data-'.now()->format('Y-m-d').'.json"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    public function requestDeletion(RequestAccountDeletionRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->deletion_requested_at === null) {
            $user->forceFill(['deletion_requested_at' => now()])->save();
        }

        return redirect()->route('account.data')->with('status', __('account.data.deletion_sent'));
    }
}
