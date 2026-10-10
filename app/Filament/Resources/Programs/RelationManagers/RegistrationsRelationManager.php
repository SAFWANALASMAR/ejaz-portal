<?php

namespace App\Filament\Resources\Programs\RelationManagers;

use App\Actions\Programs\RecordProgramAttendance;
use App\Enums\RegistrationStatus;
use App\Filament\Exports\ProgramRegistrationExporter;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Support\SaudiPhone;
use Filament\Actions\BulkAction;
use Filament\Actions\ExportAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * المسجلون في البرنامج: الاطلاع بصلاحية البرنامج، وتسجيل الحضور (programs.update)، والتصدير إلى
 * Excel (programs.export). ولا تُعدَّل بيانات المسجل من هنا.
 */
class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('programs.registrations.plural');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('programs.registrations.model'))
            ->pluralModelLabel(__('programs.registrations.plural'))
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('user.name')->label(__('programs.registrations.fields.name'))->searchable(),
                TextColumn::make('user.email')->label(__('programs.registrations.fields.email')),
                TextColumn::make('user.phone')->label(__('programs.registrations.fields.phone'))->formatStateUsing(fn (?string $state): string => SaudiPhone::display($state)),
                TextColumn::make('status')->label(__('programs.registrations.fields.status'))->badge(),
                TextColumn::make('attended_at')->label(__('programs.registrations.fields.attended_at'))->dateTime('Y-m-d H:i')->placeholder('—'),
                IconColumn::make('certificate_issued_at')->label(__('programs.certificate.issued'))->boolean()->state(fn ($record): bool => $record->certificate_issued_at !== null),
                TextColumn::make('created_at')->label(__('programs.registrations.fields.registered_at'))->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('programs.registrations.fields.status'))->options(RegistrationStatus::class),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label(__('programs.registrations.actions.export'))
                    ->exporter(ProgramRegistrationExporter::class)
                    ->authorize(fn (): bool => auth()->user()?->can('export', Program::class) ?? false),
            ])
            ->toolbarActions([
                $this->attendanceAction('markAttended', true, Heroicon::OutlinedCheckCircle),
                $this->attendanceAction('unmarkAttended', false, Heroicon::OutlinedXCircle),
            ]);
    }

    private function attendanceAction(string $name, bool $attended, Heroicon $icon): BulkAction
    {
        return BulkAction::make($name)
            ->label(__('programs.registrations.actions.'.($attended ? 'mark_attended' : 'unmark_attended')))
            ->icon($icon)
            ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
            ->action(function (Collection $records, RecordProgramAttendance $attendance) use ($attended): void {
                /** @var User $actor */
                $actor = auth()->user();
                /** @var Program $program */
                $program = $this->getOwnerRecord();
                /** @var Collection<int, ProgramRegistration> $records */
                try {
                    $count = $attendance->handle($actor, $program, $records, $attended);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('programs.registrations.attendance_saved', ['count' => number_format($count)]))->send();
            });
    }
}
