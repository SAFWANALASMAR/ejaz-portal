<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * التنقّل السريع في الواجهة العامة (wire:navigate — docs/decisions.md): رابط صفحة داخلية يجلبه Livewire ويضعه
 * مكان الحالية بلا إعادة تحميل. وما سواه تحميل كامل: الرابط الخارجي، والرابط الموقّع، وما لا مسار مسمى له،
 * وتحويلات روابط الموقع القديم (المسار الاحتياطي)، ومسارات FULL_PAGE_ROUTES.
 */
final class SpaLink
{
    /**
     * مسارات تُحمَّل تحميلاً كاملاً ولو كانت على الموقع نفسه.
     */
    public const FULL_PAGE_ROUTES = [
        // الدخول والخروج والتسجيل والتحقق واستعادة كلمة المرور وتأكيدها
        'login', 'logout', 'register', 'password.*', 'two-factor.*', 'verification.*', 'account.phone.verify',
        // عرض الملفات وتنزيلها وطباعتها: ردود ملفات لا صفحات
        '*.file', '*.download', '*.pdf', '*.print', '*.certificate', '*.attachment', 'assembly.meetings.minutes',
        // وصفحة النموذج المعبأ في «نماذجي» مستند طباعة مستقل بأنماطه لا بقالب الموقع
        'account.forms.show',
        'account.data.export', 'competitions.works.show', 'competitions.reviews.*', 'competitions.videos.status',
        'competitions.exam.intro.video', 'competitions.exam.media', 'registers.attachment', 'storage.local',
        // الدفع والتبرع، والمتجر (تحويل إلى متجر سلة)
        'donate', 'payments.*', 'store.*',
        // صفحة الاستبيان: سياسة أمان المحتوى فيها تسمح بعنصر التحدي وحدها، ولا تتغير السياسة بالتنقّل
        'surveys.show',
        // لوحة الإدارة بوضعها الخاص، ولوحة الطوابير، وخريطة الموقع وrobots
        'filament.*', 'horizon.*', 'sitemap', 'robots',
    ];

    /**
     * هل يحمل الرابط wire:navigate؟
     */
    public static function allows(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        return once(fn (): bool => self::navigable($url));
    }

    /**
     * هل الصفحة الحالية من صفحات التنقّل السريع؟ صفحة تُحمَّل كاملاً لا يُحمَّل فيها Livewire، فروابطها تحميل
     * كامل أيضاً: لا تبقى سياسة أمان خاصة بها (عنصر التحدي في الاستبيان) على ما يُتنقّل إليه منها.
     */
    public static function currentPage(Request $request): bool
    {
        return $request->route() instanceof RoutingRoute && ! $request->routeIs(...self::FULL_PAGE_ROUTES);
    }

    private static function navigable(string $url): bool
    {
        $parts = parse_url($url);

        // مسار نسبي من الجذر، أو رابط مطلق على مضيف البوابة نفسه (route() وurl() من APP_URL)
        $sameSite = isset($parts['host'])
            ? in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                && strtolower($parts['host']) === strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST))
            : str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\');

        if ($parts === false || ! $sameSite) {
            return false;
        }

        parse_str($parts['query'] ?? '', $query);

        if (isset($query['signature'])) {
            return false;
        }

        $request = Request::create($url);
        $path = '/'.ltrim($request->path(), '/');

        // ما يبدأ بالجزء الثابت من المسار وحده يُفحص بـ matches()، فلا تمر كل روابط الصفحة على كل المسارات
        $match = collect(self::pageRoutes())
            ->first(fn (array $entry): bool => str_starts_with($path, $entry[0]) && $entry[1]->matches($request));
        $name = $match ? $match[1]->getName() : null;

        // ومع route:cache يأخذ المسار بلا اسم اسماً مولّداً (generated::)، فيبقى بلا اسم كما في التطوير
        return $name !== null && ! str_starts_with($name, 'generated::') && ! Str::is(self::FULL_PAGE_ROUTES, $name);
    }

    /**
     * مسارات GET بلا المسار الاحتياطي، كل منها بجزئه الثابت قبل أول معامل، مرة لكل طلب. والمطابقة بـ matches() لا
     * بـ match() من المجموعة: تلك تربط معاملات الرابط بنسخة المسار المحفوظة نفسها التي يخدم بها الطلب الحالي (في
     * RouteCollection، وفي nameCache مع route:cache)، فتغيّر معاملاته.
     *
     * @return list<array{0: string, 1: RoutingRoute}>
     */
    private static function pageRoutes(): array
    {
        return once(fn (): array => collect(Route::getRoutes()->get('GET'))
            ->reject(fn (RoutingRoute $route): bool => $route->isFallback)
            // بلا الشرطة الأخيرة، فيطابق '/x' المسارَ 'x/{optional?}' أيضاً
            ->map(fn (RoutingRoute $route): array => ['/'.trim(Str::before($route->uri(), '{'), '/'), $route])
            ->values()
            ->all());
    }
}
