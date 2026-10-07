<?php

namespace App\Filament\Exports;

use App\Enums\FormFieldType;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

/**
 * تصدير الطلبات الإلكترونية إلى Excel في الطابور. الملف في القرص الخاص، ولا ينزّله إلا من طلبه
 * (DownloadExport في Filament). والمسودات لا تُصدَّر، ولا المرفقات (أسماؤها فقط).
 */
class ServiceRequestExporter extends Exporter
{
    protected static ?string $model = ServiceRequest::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference')->label(__('services.admin.fields.reference')),
            ExportColumn::make('opening.title')->label(__('services.admin.fields.opening'))->formatStateUsing(fn (?string $state) => self::safeCell($state)),
            ExportColumn::make('opening.kind')->label(__('services.admin.fields.kind'))->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('user.name')->label(__('services.admin.fields.applicant'))->formatStateUsing(fn (?string $state) => self::safeCell($state)),
            ExportColumn::make('user.email')->label(__('services.admin.fields.email')),
            ExportColumn::make('user.phone')->label(__('services.admin.fields.phone')),
            ExportColumn::make('status')->label(__('services.admin.fields.status'))->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('assignee.name')->label(__('services.admin.fields.assignee')),
            ExportColumn::make('submitted_at')->label(__('services.admin.fields.submitted_at'))
                ->formatStateUsing(fn ($state) => $state?->setTimezone(config('app.display_timezone'))->format('Y-m-d H:i')),
            ExportColumn::make('answers')->label(__('services.admin.fields.answers'))
                ->state(fn (ServiceRequest $record): ?string => self::safeCell(self::answersText($record))),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->where('status', '!=', ServiceRequestStatus::Draft->value)->with(['opening', 'user', 'assignee']);
    }

    public function getFileDisk(): string
    {
        return ServiceRequest::disk();
    }

    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('services.admin.export_done', ['count' => number_format($export->successful_rows)]);

        if (($failed = $export->getFailedRowsCount()) > 0) {
            $body .= ' '.__('services.admin.export_failed', ['count' => number_format($failed)]);
        }

        return $body;
    }

    /**
     * الإجابات «اسم الحقل: القيمة» سطراً سطراً، والمرفق باسمه.
     */
    private static function answersText(ServiceRequest $record): string
    {
        return collect($record->opening->fields())
            ->map(fn (array $field): string => $field['label'].': '.(string) ($field['type'] === FormFieldType::Pdf
                ? ($record->files[$field['key']]['name'] ?? '')
                : ($record->answers[$field['key']] ?? '')))
            ->implode("\n");
    }

    /**
     * نص يبدأ بـ = أو + أو - أو @ يُقرأ صيغةً إن حُفظ الملف CSV، فيُسبق بعلامة اقتباس.
     */
    public static function safeCell(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
