<?php

namespace App\Http\Middleware;

use App\Models\AssistantSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * المساعد المعطّل من اللوحة: مساراته ترد 404، كما لا يظهر زره (requirements/10).
 */
class EnsureAssistantEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(AssistantSettings::current()->is_enabled, 404);

        return $next($request);
    }
}
