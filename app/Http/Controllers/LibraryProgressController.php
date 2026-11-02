<?php

namespace App\Http\Controllers;

use App\Actions\Library\RecordLibraryProgress;
use App\Http\Requests\RecordLibraryProgressRequest;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;

/**
 * بلاغ تقدّم القارئ والمشغّل («قراءاتي» — docs/decisions/2026-11-01-my-reading.md): لمسجّل الدخول، بحده ورمز CSRF. ويرد
 * الصفحات المقبولة من البلاغ، فيحتفظ القارئ بالباقي لبلاغ تالٍ. والتحقق في الطلب، والحدود تحت القفل في الإجراء.
 */
class LibraryProgressController extends Controller
{
    public function __invoke(RecordLibraryProgressRequest $request, RecordLibraryProgress $record): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $result = $record->handle($user, $request->item(), $request->progress());
        } catch (LockTimeoutException) {
            // بلاغ آخر للحساب نفسه لم ينته: يُعاد لاحقاً كتجاوز الحد
            return response()->json(['pages' => []], 429);
        }

        return response()->json(['pages' => $result['pages']]);
    }
}
