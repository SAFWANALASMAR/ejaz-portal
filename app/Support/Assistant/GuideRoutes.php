<?php

namespace App\Support\Assistant;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * الصفحات التي يُحال إليها الزائر من الدليل والأسئلة الشائعة: صفحات GET مسماة في الموقع بلا معاملات، لا لوحة الإدارة
 * ولا الملفات ولا الدفع ولا ما يخدم الآلات.
 */
final class GuideRoutes
{
    private const EXCLUDED = [
        'filament.', 'horizon.', 'livewire.', 'boost.', 'ignition.', 'sanctum.', 'storage.', 'payments.', 'donate',
        'store.catalog', 'sitemap', 'robots', 'assistant.', 'two-factor.', 'user-', 'password.confirm', 'logout',
    ];

    /**
     * @return array<string, string> اسم المسار ← اسمه المقروء ومساره
     */
    public static function options(): array
    {
        $options = [];

        foreach (app(Router::class)->getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! in_array('GET', $route->methods(), true) || $route->parameterNames() !== [] || self::excluded($name)) {
                continue;
            }

            $key = 'assistant.page_names.'.$name;
            $label = __($key) !== $key ? (string) __($key) : $name;
            $options[$name] = $label.' — /'.ltrim($route->uri(), '/');
        }

        asort($options);

        return $options;
    }

    public static function allows(string $name): bool
    {
        return array_key_exists($name, self::options());
    }

    /**
     * مسار داخلي لصفحة في الموقع (ليس المسار الاحتياطي)، مصدراً لسؤال شائع.
     */
    public static function isSitePath(string $path): bool
    {
        if (CurrentPage::path($path) === null) {
            return false;
        }

        try {
            $route = app(Router::class)->getRoutes()->match(Request::create($path));
        } catch (HttpException) {
            return false;
        }

        return ! $route->isFallback && ! self::excluded((string) $route->getName());
    }

    private static function excluded(string $name): bool
    {
        foreach (self::EXCLUDED as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
