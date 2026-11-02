<?php

namespace App\Http\Controllers\Competitions;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * صفحات المسابقات العامة: القائمة بشارات حالتها، وصفحة المسابقة بزر التقديم حين تكون مفتوحة.
 * المنشورة وحدها، وما سواها 404.
 */
class CompetitionController extends Controller
{
    private const PER_PAGE = 12;

    /**
     * سقف الحالية والقادمة: بلا ترقيم، والعدد في الواقع مسابقة أو اثنتان، فالسقف حماية لا أكثر.
     */
    private const ACTIVE_LIMIT = 24;

    /**
     * التصنيف باستعلامات مستقلة قبل الترقيم (البند R15 من تدقيق القبول): الحالية والقادمة في الصفحة الأولى
     * بلا ترقيم، والسابقة وحدها مرقّمة، فلا تختفي الحالية لكثرة السابقات. والحد بين الحالات لحظة واحدة،
     * فلا تقع مسابقة في قسمين ولا تسقط بينهما (كـ Competition::status).
     */
    public function index(Request $request): View
    {
        $now = now();
        $published = fn () => Competition::query()->published();

        $past = $published()->where('opens_at', '<=', $now)->where('closes_at', '<=', $now)->orderByDesc('closes_at')->orderByDesc('id')->paginate(self::PER_PAGE);

        abort_if($past->currentPage() > max(1, $past->lastPage()), 404);

        $firstPage = $past->currentPage() === 1;

        return view('competitions.index', [
            'past' => $past,
            'open' => $firstPage
                ? $published()->where('opens_at', '<=', $now)->where('closes_at', '>', $now)->orderByDesc('opens_at')->orderBy('id')->limit(self::ACTIVE_LIMIT)->get()
                : collect(),
            'upcoming' => $firstPage
                ? $published()->where('opens_at', '>', $now)->orderBy('opens_at')->orderBy('id')->limit(self::ACTIVE_LIMIT)->get()
                : collect(),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $competition = Competition::query()->published()->where('slug', $slug)->firstOrFail();
        $user = $request->user();

        /** @var CompetitionSubmission|null $submission */
        $submission = $user === null ? null : $competition->submissions()->where('user_id', $user->id)->first();

        $response = response()->view('competitions.show', ['competition' => $competition, 'submission' => $submission]);

        // الصفحة لصاحب الحساب تحمل رقم مشاركته، فلا تُحفظ في ذاكرة متصفح ولا وسيط
        return $user === null ? $response : $response->header('Cache-Control', 'private, no-store');
    }

    /**
     * الرابط المختصر لمسابقة طلاب منشورة (/c/{code}) يحوّل إلى صفحتها، فيبقى ثابتاً لو تغيّر رابطها.
     */
    public function short(string $code): RedirectResponse
    {
        $competition = Competition::query()->published()->where('student_mode', true)->where('short_code', $code)->firstOrFail();

        return redirect()->route('competitions.show', $competition->slug);
    }

    /**
     * دليل الدخول إلى الاختبار: صفحة عامة من نصوص المسابقة في اللوحة، و404 إن لم يُكتب.
     */
    public function guide(string $slug): View
    {
        $competition = Competition::query()->published()->where('student_mode', true)->where('slug', $slug)->firstOrFail();
        abort_unless($competition->hasGuide(), 404);

        return view('competitions.student.guide', ['competition' => $competition]);
    }
}
