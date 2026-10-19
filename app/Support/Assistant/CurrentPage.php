<?php

namespace App\Support\Assistant;

use App\Models\AssistantChunk;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * الصفحة الحالية للزائر من مسارها وحده (بلا استعلام ولا بيانات)، ليجيب المساعد «أنت الآن في...». والاسم من مقطع
 * منشور بالمسار نفسه، أو من أسماء الصفحات العامة في lang/ar/assistant.php، وإلا فالمسار وحده.
 */
final class CurrentPage
{
    /**
     * المسار الموحّد إن كان مسار صفحة في الموقع، وإلا null. والمسار الذي فيه رمز أو معرّف (رمز إعادة كلمة المرور،
     * أو uuid طلب، أو رقم سجل) لا يُقبل، فلا يصل إلى المزود ولا إلى سجله (الفحص الأمني 19 أكتوبر)
     */
    public static function path(?string $path): ?string
    {
        if ($path === null || preg_match('#^/[\p{L}\p{N}/._\-]*$#u', $path) !== 1 || str_contains($path, '//') || str_contains($path, '..')) {
            return null;
        }

        if (preg_match('#/(\d+|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|[0-9a-z]{32,})(/|$)#i', $path) === 1) {
            return null;
        }

        return mb_substr($path, 0, 300);
    }

    public static function name(string $path): ?string
    {
        $title = AssistantChunk::query()->where('url', $path)->value('title');

        if (is_string($title)) {
            return $title;
        }

        try {
            $route = app(Router::class)->getRoutes()->match(Request::create($path));
        } catch (HttpException) {
            return null;
        }

        $key = 'assistant.page_names.'.$route->getName();

        return $route->getName() !== null && __($key) !== $key ? (string) __($key) : null;
    }
}
