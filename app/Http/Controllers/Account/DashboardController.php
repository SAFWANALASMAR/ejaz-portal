<?php

namespace App\Http\Controllers\Account;

use App\Actions\Accounts\BuildAccountSummary;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BuildAccountSummary $summary): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('account.dashboard', ['user' => $user, 'summary' => $summary->handle($user)]);
    }
}
