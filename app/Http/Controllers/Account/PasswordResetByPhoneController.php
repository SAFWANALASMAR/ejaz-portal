<?php

namespace App\Http\Controllers\Account;

use App\Actions\Accounts\RequestPasswordResetCode;
use App\Actions\Accounts\ResetPasswordByPhone;
use App\Http\Controllers\Controller;
use App\Support\SaudiPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * استعادة كلمة المرور برمز على الجوال (قرار صفوان، 20 أكتوبر 2026): طلب الرمز من «نسيت كلمة المرور»، ثم صفحة الرمز
 * وكلمة المرور الجديدة. والرقم في الجلسة بين الصفحتين. ولا يظهر الخيار ولا تعمل مساراته إلا متى كانت الرسائل النصية
 * جاهزة (ResetPasswordByPhone::available).
 */
class PasswordResetByPhoneController extends Controller
{
    private const SESSION_KEY = 'password_reset.phone';

    public function send(Request $request, RequestPasswordResetCode $requestCode): RedirectResponse
    {
        abort_unless(ResetPasswordByPhone::available(), 404);

        $phone = $requestCode->handle($request->input('phone'));
        $request->session()->put(self::SESSION_KEY, $phone);

        return redirect()->route('password.phone.reset')->with('status', __('account.password_reset.sent'));
    }

    public function edit(Request $request): View|RedirectResponse
    {
        abort_unless(ResetPasswordByPhone::available(), 404);

        $phone = $request->session()->get(self::SESSION_KEY);

        if (! is_string($phone)) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password-phone', ['phone' => SaudiPhone::display($phone)]);
    }

    public function update(Request $request, ResetPasswordByPhone $reset): RedirectResponse
    {
        abort_unless(ResetPasswordByPhone::available(), 404);

        $phone = $request->session()->get(self::SESSION_KEY);

        if (! is_string($phone)) {
            return redirect()->route('password.request');
        }

        $reset->handle($phone, $request->only(['code', 'password', 'password_confirmation']));
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login')->with('status', __('account.password_reset.done'));
    }
}
