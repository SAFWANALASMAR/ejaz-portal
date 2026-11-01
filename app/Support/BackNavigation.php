<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/**
 * شريط العودة (x-back-nav — طلب المدير التنفيذي، 29 أكتوبر 2026): الصفحة الأم المنطقية لكل صفحة داخلية من اسم مسارها، في
 * جدول واحد هنا لا في كل قالب. ومعاملات الأم من معاملات الصفحة نفسها (من عنصر مكتبة إلى قسمه). وما ليس في الجدول يعود
 * بتاريخ المتصفح (history.back في resources/js/back-nav.js)، والرئيسية بلا شريط.
 */
final class BackNavigation
{
    /**
     * اسم المسار ← [مسار الأم، ومعاملاتها المأخوذة من معاملات الصفحة].
     *
     * @var array<string, array{0: string, 1?: list<string>}>
     */
    public const PARENTS = [
        'about' => ['home'],
        'pages.show' => ['home'],
        'initiatives.index' => ['home'],
        'initiatives.show' => ['initiatives.index'],
        'governance.index' => ['home'],
        'governance.show' => ['governance.index'],
        'library.index' => ['home'],
        'library.section' => ['library.index'],
        'library.show' => ['library.section', ['section']],
        'media.index' => ['home'],
        'media.news.index' => ['media.index'],
        'media.news.show' => ['media.news.index'],
        'media.albums.index' => ['media.index'],
        'media.albums.show' => ['media.albums.index'],
        'media.announcements' => ['media.index'],
        'media.partners' => ['media.index'],
        'programs.index' => ['home'],
        'programs.show' => ['programs.index'],
        'programs.register' => ['programs.show', ['slug']],
        'competitions.index' => ['home'],
        'competitions.show' => ['competitions.index'],
        'competitions.entry' => ['competitions.show', ['slug']],
        'competitions.student.guide' => ['competitions.show', ['slug']],
        'competitions.student.register' => ['competitions.show', ['slug']],
        'competitions.student.round' => ['account.competitions'],
        'competitions.exam' => ['account.competitions'],
        'services.index' => ['home'],
        'services.assembly-membership' => ['services.index'],
        'services.jobs' => ['services.index'],
        'services.volunteering' => ['services.index'],
        'services.openings.show' => ['services.index'],
        'services.apply' => ['services.openings.show', ['slug']],
        'surveys.index' => ['media.index'],
        'contact' => ['home'],
        'live' => ['home'],
        'privacy' => ['home'],
        'account.dashboard' => ['home'],
        'account.profile' => ['account.dashboard'],
        'account.data' => ['account.dashboard'],
        'account.security' => ['account.dashboard'],
        'account.phone.verify' => ['account.dashboard'],
        'account.payments' => ['account.dashboard'],
        'account.competitions' => ['account.dashboard'],
        'account.programs.index' => ['account.dashboard'],
        'account.requests.index' => ['account.dashboard'],
        'account.requests.show' => ['account.requests.index'],
        'account.forms.index' => ['account.dashboard'],
        'account.forms.show' => ['account.forms.index'],
        'assembly.index' => ['account.dashboard'],
        'assembly.meetings.index' => ['assembly.index'],
        'assembly.meetings.show' => ['assembly.meetings.index'],
        'assembly.minutes.index' => ['assembly.index'],
        'assembly.files.index' => ['assembly.index'],
        'login' => ['home'],
        'register' => ['login'],
        'password.request' => ['login'],
        'password.phone.reset' => ['password.request'],
    ];

    /**
     * مسارات لا شريط فيها: الرئيسية، والاختبار الجاري (لا يُغادر بنقرة عابرة).
     */
    public const HIDDEN = ['home', 'competitions.exam.question', 'competitions.exam.review', 'competitions.student.unit', 'competitions.student.review'];

    /**
     * يظهر الشريط في هذه الصفحة؟
     */
    public static function shows(?Route $route): bool
    {
        return $route !== null && $route->getName() !== null && ! in_array($route->getName(), self::HIDDEN, true);
    }

    /**
     * رابط الصفحة الأم، أو null فيعود الزر بتاريخ المتصفح.
     */
    public static function parentUrl(?Route $route): ?string
    {
        $name = $route?->getName();

        if ($name === null || ! isset(self::PARENTS[$name])) {
            return null;
        }

        [$parent, $keys] = self::PARENTS[$name] + [1 => []];

        if (! Router::has($parent)) {
            return null;
        }

        $parameters = [];

        foreach ($keys as $key) {
            $value = $route->parameter($key);
            $value = match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof Model => $value->getRouteKey(),
                default => $value,
            };

            if (! is_string($value) && ! is_int($value)) {
                return null;
            }

            $parameters[$key] = $value;
        }

        return route($parent, $parameters);
    }
}
