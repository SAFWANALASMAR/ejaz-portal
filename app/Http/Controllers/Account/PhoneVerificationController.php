<?php

namespace App\Http\Controllers\Account;

use App\Actions\Accounts\SendPhoneVerificationCode;
use App\Actions\Accounts\VerifyPhoneCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\VerifyPhoneRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhoneVerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedPhone()) {
            return redirect()->route('account.dashboard')->with('status', __('account.phone.already_verified'));
        }

        return view('account.verify-phone', ['user' => $user]);
    }

    public function send(Request $request, SendPhoneVerificationCode $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedPhone()) {
            return redirect()->route('account.dashboard');
        }

        $action->handle($user);

        return back()->with('status', __('account.phone.sent'));
    }

    public function verify(VerifyPhoneRequest $request, VerifyPhoneCode $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->handle($user, $request->string('code')->toString());

        return redirect()->intended(route('account.dashboard'))->with('status', __('account.phone.verified'));
    }
}
