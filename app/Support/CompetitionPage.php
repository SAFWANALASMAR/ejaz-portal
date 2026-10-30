<?php

namespace App\Support;

/**
 * نصوص صفحة مسابقة الطلاب ودليل الدخول إليها (page_content)، تُكتب من اللوحة (مطوية «البراهين العلمية»): فقرات،
 * وقوائم بنود (سطر لكل بند). والعناوين من lang/ar/competitions.php (student.page)، والنصوص للجمعية وحدها.
 */
final class CompetitionPage
{
    /**
     * فقرات الصفحة بترتيب عرضها.
     */
    public const PARAGRAPHS = ['intro', 'idea', 'vision', 'mission', 'goal'];

    /**
     * قوائم الصفحة بترتيب عرضها بعد الفقرات.
     */
    public const LISTS = ['goals', 'outcomes', 'rules', 'schools'];

    /**
     * أقسام دليل الدخول إلى الاختبار (صفحته العامة).
     */
    public const GUIDE = ['guide_before', 'guide_steps', 'guide_alerts', 'guide_problems'];

    /**
     * سطر الجهة المنسَّق معها («بالتنسيق مع: …»).
     */
    public const PARTNER = 'partner';

    /**
     * مقدمة «مراحل المسابقة» فوق بطاقات المراحل (فقرة).
     */
    public const ROUNDS_INTRO = 'rounds_intro';

    public const PARAGRAPH_MAX = 3000;

    public const ITEM_MAX = 600;

    public const ITEMS_MAX = 30;

    /**
     * @return list<string>
     */
    public static function listKeys(): array
    {
        return [...self::LISTS, ...self::GUIDE];
    }

    /**
     * قائمة من نص بأسطر أو من مصفوفة: البنود غير الفارغة بلا مسافات زائدة.
     *
     * @return list<string>
     */
    public static function items(mixed $value): array
    {
        $lines = is_array($value) ? $value : preg_split('/\R/u', (string) $value);

        return array_values(array_filter(array_map(fn (mixed $line): string => is_scalar($line) ? trim((string) $line) : '', $lines ?: []), fn (string $line): bool => $line !== ''));
    }
}
