<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\FormEntries\FormEntryResource;
use App\Models\FormEntry;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * ملف الموظف أو المشارك: النماذج المعبأة المحفوظة فيه («حفظ النماذج تلقائيًا في ملفات الموظفين أو
 * المشاركين» — requirements/12 البند 10). للاطلاع وحده، لمن يطّلع على المعبأ (form_entries.view)، والتعبئة
 * والتعديل من شاشة النماذج المعبأة.
 */
class FormEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'formEntries';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('documents.entries.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewAny', FormEntry::class) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('documents.entries.model'))
            ->pluralModelLabel(__('documents.entries.plural'))
            // ما يطّلع عليه الحساب في اللوحة وحده (FormEntry::scopeVisibleInPanel)
            ->modifyQueryUsing(fn (Builder $query) => $query->with('version.template')->scopes(['visibleInPanel' => [auth()->user()]]))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('reference')->label(__('documents.fields.reference')),
                TextColumn::make('version.template.title')->label(__('documents.fields.template'))->wrap(),
                TextColumn::make('status')->label(__('documents.fields.status'))->badge(),
                TextColumn::make('created_at')->label(__('documents.fields.created_at'))->dateTime('Y-m-d H:i'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('documents.actions.print_view'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->url(fn (FormEntry $record): string => route('documents.entries.print', $record), shouldOpenInNewTab: true),
            ])
            ->recordUrl(fn (FormEntry $record): string => FormEntryResource::getUrl('view', ['record' => $record]));
    }
}
