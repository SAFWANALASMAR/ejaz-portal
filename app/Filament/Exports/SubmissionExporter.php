<?php

namespace App\Filament\Exports;

use App\Models\CompetitionSubmission;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * التصدير الكامل لمشاركات مسابقة (لوحة المتابعة): يُبنى في الطابور، ولمن له submissions.export.
 * وفيه بيانات المتقدمين للتواصل، فلا ينزّله إلا من طلبه (ExportPolicy)، ويُحذف بعد يوم (ejaz:prune-exports).
 * والنصوص التي يكتبها المتقدم تُحمى من حقن الصيغ في برامج الجداول.
 */
class SubmissionExporter extends Exporter
{
    protected static ?string $model = CompetitionSubmission::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference')->preventFormulaInjection()->label(__('competitions.entry.reference')),
            ExportColumn::make('user.name')->preventFormulaInjection()->label(__('competitions.export.name')),
            ExportColumn::make('user.email')->preventFormulaInjection()->label(__('competitions.export.email')),
            ExportColumn::make('user.phone')->preventFormulaInjection()->label(__('competitions.export.phone')),
            ExportColumn::make('status')->label(__('competitions.fields.status'))->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('category')->preventFormulaInjection()->label(__('competitions.entry.category')),
            ExportColumn::make('region')->label(__('competitions.entry.region'))->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('submitted_at')->label(__('competitions.entry.submitted_at'))
                ->formatStateUsing(fn ($state) => $state?->timezone(config('app.display_timezone'))->format('Y-m-d H:i')),
            ExportColumn::make('auto_score')->label(__('competitions.submissions.auto_score')),
            ExportColumn::make('final_score')->label(__('competitions.submissions.final_score')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return __('competitions.export.done', ['count' => number_format($export->successful_rows)]);
    }

    public function getFileDisk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }
}
