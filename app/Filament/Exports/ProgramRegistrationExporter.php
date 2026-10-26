<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\XlsxOnly;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Support\SaudiPhone;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * تصدير المسجلين في برنامج إلى Excel (requirements/03) في الطابور. الملف في القرص الخاص، ولا
 * ينزّله إلا من طلبه، ويُحذف بعد يوم (ejaz:prune-exports).
 */
class ProgramRegistrationExporter extends Exporter
{
    use XlsxOnly;

    protected static ?string $model = ProgramRegistration::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('program.title')->label(__('programs.registrations.fields.program'))->formatStateUsing(fn (?string $state) => ServiceRequestExporter::safeCell($state)),
            ExportColumn::make('user.name')->label(__('programs.registrations.fields.name'))->formatStateUsing(fn (?string $state) => ServiceRequestExporter::safeCell($state)),
            ExportColumn::make('user.email')->label(__('programs.registrations.fields.email'))->formatStateUsing(fn (?string $state) => ServiceRequestExporter::safeCell($state)),
            ExportColumn::make('user.phone')->label(__('programs.registrations.fields.phone'))->formatStateUsing(fn (?string $state) => SaudiPhone::display($state)),
            ExportColumn::make('status')->label(__('programs.registrations.fields.status'))->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('attended_at')->label(__('programs.registrations.fields.attended_at'))
                ->formatStateUsing(fn ($state) => $state?->setTimezone(config('app.display_timezone'))->format('Y-m-d H:i')),
            ExportColumn::make('created_at')->label(__('programs.registrations.fields.registered_at'))
                ->formatStateUsing(fn ($state) => $state?->setTimezone(config('app.display_timezone'))->format('Y-m-d H:i')),
            ExportColumn::make('answers')->label(__('programs.registrations.fields.answers'))
                ->state(fn (ProgramRegistration $record): ?string => ServiceRequestExporter::safeCell(self::answersText($record))),
        ];
    }

    public function getFileDisk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('programs.registrations.export_done', ['count' => number_format($export->successful_rows)]);

        if (($failed = $export->getFailedRowsCount()) > 0) {
            $body .= ' '.__('programs.registrations.export_failed', ['count' => number_format($failed)]);
        }

        return $body;
    }

    private static function answersText(ProgramRegistration $record): string
    {
        /** @var Program $program */
        $program = $record->program;

        return collect($program->fields())
            ->map(fn (array $field): string => $field['label'].': '.(string) ($record->answers[$field['key']] ?? ''))
            ->implode("\n");
    }
}
