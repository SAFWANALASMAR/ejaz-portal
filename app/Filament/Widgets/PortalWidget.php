<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\Portals;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * «بوابتي» في رئيسية اللوحة: لكل فئة ينتمي إليها الحساب (requirements/12 §12) قدراتها بنصوص المتطلبات،
 * وكل قدرة رابط إلى شاشتها إن كان يملك فتحها (Portals).
 *
 * وكل قدرة بطاقة بأيقونة شاشتها في القائمة الجانبية (طلب لوحة الإدارة، 4 نوفمبر 2026 — الدفعة ٢)، فتتطابق البطاقة والقائمة.
 */
class PortalWidget extends Widget
{
    protected static ?int $sort = 0;

    // أول ما في الرئيسية بعد التحية، ولا استعلام فيه غير فحص الصلاحيات، فيُعرض مع الصفحة لا مربعاً فارغاً ينتظر طلباً مؤجلاً
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.portal-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * قدرات بلا شاشة خاصة بها، أو تشارك غيرها الشاشة نفسها، فلها أيقونة تميزها.
     *
     * @var array<string, Heroicon>
     */
    private const ICONS = [
        'stats' => Heroicon::OutlinedChartBar,
        'attendance' => Heroicon::OutlinedClipboardDocumentCheck,
    ];

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && Portals::for($user) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $definitions = Portals::definitions();

        // «الإحصائيات العامة» في هذه الصفحة: البطاقة تنقل إلى أول مجموعات الإحصاء لمن يراها (ملاحظة المقيّم على #168)
        $statsUrl = PeopleStatsWidget::canView() ? '#'.PeopleStatsWidget::ANCHOR : null;

        $portals = array_map(fn (array $portal): array => [
            ...$portal,
            'columns' => self::columns(count($portal['items'])),
            'items' => array_map(fn (array $item): array => [
                ...$item,
                'url' => $item['url'] ?? ($item['key'] === 'stats' ? $statsUrl : null),
                'icon' => self::icon($item['key'], $definitions[$portal['key']]['items'][$item['key']] ?? null),
            ], $portal['items']),
        ], Portals::for($user));

        return ['portals' => $portals];
    }

    /**
     * أعمدة البطاقات على الحاسوب بعددها، فلا تبقى بطاقة وحيدة في سطر ولا تنكسر العناوين في أعمدة ضيقة: حتى ثلاث في سطر واحد،
     * والأربع اثنتان واثنتان، والخمس فأكثر ثلاث في السطر (ملاحظة المقيّم على #168).
     */
    private static function columns(int $count): int
    {
        return match (true) {
            $count <= 3 => max(1, $count),
            $count === 4 => 2,
            default => 3,
        };
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>|null  $resource
     */
    private static function icon(string $key, ?string $resource): string|BackedEnum|Htmlable
    {
        return self::ICONS[$key] ?? ($resource !== null ? $resource::getNavigationIcon() : null) ?? Heroicon::OutlinedSquares2x2;
    }
}
