<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * وحدة مؤرشفة خلف مفتاح (config/<الوحدة>.php: enabled): مساراتها تبقى مسجّلة وترد 404 ما دام معطّلاً،
 * فيعود تشغيلها بالمفتاح وحده. مثال: feature:payments
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless((bool) config($feature.'.enabled'), 404);

        return $next($request);
    }
}
