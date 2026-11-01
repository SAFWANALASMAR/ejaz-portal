<?php

namespace App\Http\Controllers\Assembly;

use App\Actions\Assembly\BuildBoardPortal;
use App\Http\Controllers\Controller;
use App\Support\AssemblyArea;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * صفحة قسم الجمعية العمومية (الصورة 05)، عامة: رأس القسم ثم البلاطات. للزائر البلاطات الأربع، ولمن يصل إلى المنطقة بوابة
 * مجلس الإدارة والجمعية العمومية (Final-Board — BuildBoardPortal)، ولصاحب حساب غير عضو تنبيه ورابط طلب الانضمام.
 */
class AssemblyController extends Controller
{
    public function __invoke(Request $request, BuildBoardPortal $portal): Response
    {
        $user = $request->user();
        $member = $user !== null && AssemblyArea::allows($user);

        $response = response()->view('assembly.index', [
            'user' => $user,
            'member' => $member,
            'portal' => $member ? $portal->handle($user) : null,
        ]);

        // لصاحب الحساب حالته واجتماعاته، فلا تُحفظ في ذاكرة متصفح ولا وسيط
        return $user === null ? $response : $response->header('Cache-Control', 'private, no-store');
    }
}
