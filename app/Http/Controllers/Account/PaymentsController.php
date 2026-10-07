<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «مدفوعاتي» في لوحة المستخدم: عمليات صاحب الحساب وحده، بحالتها ورقم إيصالها.
 */
class PaymentsController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $payments = Payment::query()->where('user_id', $user->id)->latest()->paginate(20);

        abort_if($payments->currentPage() > max(1, $payments->lastPage()), 404);

        return view('account.payments', [
            'payments' => $payments,
            'donationsEnabled' => (bool) (Setting::group('donations')['enabled'] ?? false),
        ]);
    }
}
