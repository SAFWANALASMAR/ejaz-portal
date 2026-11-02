<?php

namespace App\Http\Controllers\Account;

use App\Actions\Accounts\BuildAccountSummary;
use App\Actions\Accounts\BuildOversight;
use App\Actions\Accounts\BuildParticipationStats;
use App\Actions\Content\BuildHomePage;
use App\Actions\Library\BuildMyReading;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\ProgramRegistration;
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
            // «برامجي» من برامج المكتبة بتقدّمها، وعدد «برامج أتممتها» (docs/decisions/2026-11-02-reading-program.md)
            'libraryPrograms' => ProgramRegistration::query()->whereBelongsTo($user)
                ->where('status', RegistrationStatus::Confirmed->value)->whereNull('completed_at')
                ->whereHas('program', fn ($program) => $program->whereNotNull('library_item_id'))
                ->with(['program.type', 'program.libraryItem'])->latest('id')->limit(self::READING_ITEMS)->get(),
            'completedPrograms' => ProgramRegistration::query()->whereBelongsTo($user)->whereNotNull('completed_at')->count(),
            // شريط «جديد مكتبة إعجاز وقناتها» (Final-Dashboard): محتوى عام منشور واحد لكل الحسابات، فيُخزَّن نصاً مع المحتوى العام
            'library' => new HtmlString(ContentCache::remember('account.library-strip', fn (): string => view('account.partials.library-strip', BuildHomePage::libraryStrip())->render())),
        ])->header('Cache-Control', 'private, no-store');
    }
}
