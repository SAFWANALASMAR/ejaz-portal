<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحة الأمان: تفعيل التحقق الثنائي وإيقافه ورموز الاسترداد.
 * العمليات نفسها عبر مسارات Fortify وتتطلب تأكيد كلمة المرور.
 */
class SecurityController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('account.security', ['user' => $request->user()]);
    }
}
