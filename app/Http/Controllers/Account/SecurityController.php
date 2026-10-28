<?php

namespace App\Http\Controllers\Account;

use App\Actions\Accounts\LogoutOtherSessions;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحة الأمان: تفعيل التحقق الثنائي وإيقافه ورموز الاسترداد، والخروج من الأجهزة الأخرى.
 * عمليات التحقق الثنائي عبر مسارات Fortify وتتطلب تأكيد كلمة المرور.
 */
class SecurityController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('account.security', ['user' => $request->user()]);
    }

    public function logoutOtherSessions(Request $request, LogoutOtherSessions $logout): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $logout->handle($user, $request->only('current_password'));

        return redirect()->route('account.security')->with('status', __('account.security.other_sessions.done'));
    }
}
