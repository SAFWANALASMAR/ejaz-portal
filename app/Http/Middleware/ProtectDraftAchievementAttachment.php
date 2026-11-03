<?php

namespace App\Http\Middleware;

use App\Models\Achievement;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Symfony\Component\HttpFoundation\Response;

/**
 * مرفق مسودة الإنجاز اطلاع إداري، فيمر بحمايتي اللوحة نفسيهما كرابط معاينة الصفحات: التحقق الثنائي ومدة الخمول
 * (docs/decisions/2026-11-03-security-lows.md). ومرفق المنشور محتوى عام لكل من معه الرابط الموقّع، فلا يمسّه، ولا تُختم
 * جلسة الزائر بمؤقت اللوحة. والزائر أمام المسودة يرفضه AchievementPolicy::viewAttachment.
 */
class ProtectDraftAchievementAttachment
{
    public function handle(Request $request, Closure $next): Response
    {
        $achievement = $request->route('achievement');

        if (! $achievement instanceof Achievement || $achievement->isPublished() || $request->user() === null) {
            return $next($request);
        }

        /** @var Response */
        return app(Pipeline::class)
            ->send($request)
            ->through([EnsureTwoFactorIsEnabled::class, AdminIdleTimeout::class])
            ->then($next);
    }
}
