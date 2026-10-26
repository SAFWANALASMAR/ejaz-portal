<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «مشاركاتي» في لوحة المستخدم: مشاركات المتقدم نفسه وحدها، بحالتها ورقمها المرجعي.
 */
class CompetitionsController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $submissions = CompetitionSubmission::query()
            ->where('user_id', $user->id)
            ->with(['competition', 'attempts.round'])
            ->latest()
            ->paginate(20);

        abort_if($submissions->currentPage() > max(1, $submissions->lastPage()), 404);

        return view('account.competitions', ['submissions' => $submissions]);
    }
}
