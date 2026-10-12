<?php

namespace App\Filament\Widgets;

use App\Enums\RegisterEntryStatus;
use App\Enums\RegisterEntryType;
use App\Models\RegisterEntry;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * المستندات ذات الصلاحية في رئيسية اللوحة لمن يطّلع على السجلات (requirements/13 §1): لكل نوع عدد المنتهي
 * والقريب من الانتهاء، وأقربها انتهاءً. باستعلامين مجمّعين لا لكل نوع.
 */
class RegistersOverviewWidget extends Widget
{
    private const NEAREST = 10;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.registers-overview';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', RegisterEntry::class) ?? false;
    }

    /**
     * @return array{data: array{counts: array<string, array{label: string, expired: int, expiring: int}>, nearest: Collection<int, RegisterEntry>}}
     */
    protected function getViewData(): array
    {
        return ['data' => $this->summary()];
    }

    /**
     * @return array{counts: array<string, array{label: string, expired: int, expiring: int}>, nearest: Collection<int, RegisterEntry>}
     */
    private function summary(): array
    {
        $counts = [];

        foreach ([RegisterEntryStatus::Expired, RegisterEntryStatus::Expiring] as $status) {
            $rows = RegisterEntry::query()->whereStatus($status)->toBase()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

            foreach ($rows as $type => $total) {
                $label = RegisterEntryType::tryFrom((string) $type)?->getLabel() ?? (string) $type;
                $counts[(string) $type] ??= ['label' => $label, 'expired' => 0, 'expiring' => 0];
                $counts[(string) $type][$status->value] = (int) $total;
            }
        }

        // القريبة من الانتهاء بأقربها، ثم المنتهية بأحدثها انتهاءً
        $nearest = RegisterEntry::query()->whereStatus(RegisterEntryStatus::Expiring)->orderBy('expires_on')->limit(self::NEAREST)->get();
        $nearest = $nearest->concat(RegisterEntry::query()->whereStatus(RegisterEntryStatus::Expired)->orderByDesc('expires_on')->limit(self::NEAREST - $nearest->count())->get());

        return ['counts' => $counts, 'nearest' => $nearest->values()];
    }
}
