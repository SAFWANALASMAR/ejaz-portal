<?php

namespace App\Http\Controllers\Account;

use App\Actions\Accounts\BuildAccountSummary;
use App\Actions\Accounts\BuildParticipationStats;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BuildAccountSummary $summary, BuildParticipationStats $stats): Response
    {
        /** @var User $user */
        $user = $request->user();

        // بيانات شخصية: لا تُحفظ في ذاكرة متصفح مشترك ولا وسيط
        return response()->view('account.dashboard', ['user' => $user, 'summary' => $summary->handle($user), 'stats' => $stats->handle($user)])
            ->header('Cache-Control', 'private, no-store');
    }
}
