<?php

namespace App\Http\Controllers\Account;

use App\Actions\Library\BuildMyReading;
use App\Actions\Library\ClearMyReading;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * صفحة «قراءاتي»: الكتب والصوتيات والمرئيات التي بدأها صاحب الحساب، بتقدّمه فيها وروابط المتابعة، ومسح سجله. له وحده، فالاستعلام
 * بحسابه ولا معرّف في الرابط.
 */
class MyReadingController extends Controller
{
    public function show(Request $request, BuildMyReading $reading): Response
    {
        /** @var User $user */
        $user = $request->user();

        return response()->view('account.my-reading', $reading->handle($user))->header('Cache-Control', 'private, no-store');
    }

    public function clear(Request $request, ClearMyReading $clear): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $clear->handle($user);

        return redirect()->route('account.my-reading')->with('status', __('reading.cleared'));
    }
}
