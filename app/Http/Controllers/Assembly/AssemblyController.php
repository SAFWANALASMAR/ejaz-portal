<?php

namespace App\Http\Controllers\Assembly;

use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Support\AssemblyArea;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * صفحة قسم الجمعية العمومية (الصورة 05)، عامة: رأس القسم ثم البلاطات. للزائر البلاطات الأربع، ولمن يصل
 * إلى المنطقة بلاطاتها الثلاث والاجتماع القادم، ولصاحب حساب غير عضو تنبيه ورابط طلب الانضمام.
 */
class AssemblyController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $member = $user !== null && AssemblyArea::allows($user);

        $response = response()->view('assembly.index', [
            'user' => $user,
            'member' => $member,
            'next' => $member ? Meeting::query()->where('status', MeetingStatus::Scheduled)->where('starts_at', '>=', now())->orderBy('starts_at')->first() : null,
        ]);

        // لصاحب الحساب حالته واجتماع قادم، فلا تُحفظ في ذاكرة متصفح ولا وسيط
        return $user === null ? $response : $response->header('Cache-Control', 'private, no-store');
    }
}
