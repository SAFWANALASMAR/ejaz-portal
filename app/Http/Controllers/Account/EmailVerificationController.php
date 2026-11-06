<?php

namespace App\Http\Controllers\Account;

use App\Actions\Accounts\SendEmailVerificationCode;
use App\Actions\Accounts\VerifyEmailCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\VerifyPhoneRequest;
use App\Models\User;
use App\Support\IntendedUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * توثيق البريد برمز من ستة أرقام (decisions/2026-11-06-email-otp.md). وصفحته verification.notice من Fortify.
 */
class EmailVerificationController extends Controller
{
    public function send(Request $request, SendEmailVerificationCode $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('account.dashboard');
        }

        $action->handle($user);

        return back()->with('status', __('account.email_code.sent'));
    }

    public function verify(VerifyPhoneRequest $request, VerifyEmailCode $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('account.dashboard');
        }

        $action->handle($user, $request->string('code')->toString());
        IntendedUrl::dropAdminFor($request, $user);

        return redirect()->intended(route('account.dashboard'))->with('status', __('account.email_code.verified'));
    }
}
