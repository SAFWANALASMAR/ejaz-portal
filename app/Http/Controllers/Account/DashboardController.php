<?php

namespace App\Http\Controllers\Account;

use App\Actions\Accounts\BuildAccountSummary;
use App\Actions\Accounts\BuildOversight;
use App\Actions\Accounts\BuildParticipationStats;
use App\Actions\Content\BuildHomePage;
use App\Actions\Library\BuildMyReading;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ContentCache;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;

class DashboardController extends Controller
{
    /**
     * أحدث عناصر «قراءاتي» في الرئيسية، والبقية في صفحتها.
     */
    private const READING_ITEMS = 3;

    public function __invoke(Request $request, BuildAccountSummary $summary, BuildParticipationStats $stats, BuildOversight $oversight, BuildMyReading $reading): Response
    {
        /** @var User $user */
        $user = $request->user();

        // بيانات شخصية: لا تُحفظ في ذاكرة متصفح مشترك ولا وسيط
        return response()->view('account.dashboard', [
            'user' => $user,
            'summary' => $summary->handle($user),
            'stats' => $stats->handle($user),
            'oversight' => $oversight->handle($user),
            // «قراءاتي»: أحدث ما بدأه في المكتبة ومجاميعه (docs/decisions/2026-11-01-my-reading.md)
            'reading' => $reading->handle($user, self::READING_ITEMS),
            // شريط «جديد مكتبة إعجاز وقناتها» (Final-Dashboard): محتوى عام منشور واحد لكل الحسابات، فيُخزَّن نصاً مع المحتوى العام
            'library' => new HtmlString(ContentCache::remember('account.library-strip', fn (): string => view('account.partials.library-strip', BuildHomePage::libraryStrip())->render())),
        ])->header('Cache-Control', 'private, no-store');
    }
}
