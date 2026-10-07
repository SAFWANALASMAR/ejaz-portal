<?php

namespace App\Http\Controllers\Competitions;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * صفحات المسابقات العامة: القائمة بشارات حالتها، وصفحة المسابقة بزر التقديم حين تكون مفتوحة.
 * المنشورة وحدها، وما سواها 404.
 */
class CompetitionController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request): View
    {
        $competitions = Competition::query()->published()->orderByDesc('opens_at')->orderBy('id')->paginate(self::PER_PAGE);

        abort_if($competitions->currentPage() > max(1, $competitions->lastPage()), 404);

        return view('competitions.index', ['competitions' => $competitions]);
    }

    public function show(Request $request, string $slug): View
    {
        $competition = Competition::query()->published()->where('slug', $slug)->firstOrFail();
        $user = $request->user();

        /** @var CompetitionSubmission|null $submission */
        $submission = $user === null ? null : $competition->submissions()->where('user_id', $user->id)->first();

        return view('competitions.show', ['competition' => $competition, 'submission' => $submission]);
    }
}
