<?php

namespace App\Support\Assistant;

use App\Enums\AssistantSource;
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
     * المسار الموحّد إن كان مسار صفحة في الموقع، وإلا null. للاستعمال في الخادم وحده (البحث والاسم)، ولا يُرسل إلى
     * المزود: إليه قالب المسار (template).
     */
    public static function path(?string $path): ?string
    {
        if ($path === null || preg_match('#^/[\p{L}\p{N}/._\-]*$#u', $path) !== 1 || str_contains($path, '//') || str_contains($path, '..')) {
            return null;
        }

        return mb_substr($path, 0, 300);
    }

    /**
     * قالب مسار الصفحة بلا قيمه (/reset-password/{token}، /pages/{slug})، وهو وحده ما يصل إلى المزود. فلا يصله رمز
     * إعادة كلمة المرور ولا معرّف سجل ولا أي قيمة في المسار، أياً كان شكلها (اختبار الأمن قبل الرفع، مراجعة #61).
     * والمسار الاحتياطي (صفحة غير موجودة) null.
     */
    public static function template(string $path): ?string
    {
        try {
            $route = app(Router::class)->getRoutes()->match(Request::create($path));
        } catch (HttpException) {
            return null;
        }

        return $route->isFallback ? null : '/'.ltrim(preg_replace('/\{([^}?:]+)[^}]*\}/', '{$1}', $route->uri()) ?? '', '/');
    }

    public static function name(string $path): ?string
    {
        // مقالة الدليل والسؤال الشائع يشيران إلى الصفحة ولا يسمّيانها (مراجعة ما بعد v0.12.0)
        $title = AssistantChunk::query()->where('url', $path)
            ->whereNotIn('source_type', [AssistantSource::Guide->value, AssistantSource::Faq->value])->value('title');

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
