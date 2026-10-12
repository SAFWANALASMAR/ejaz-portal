<?php

namespace App\Filament\Resources\Meetings\RelationManagers;

use App\Actions\Registers\SaveAgendaItem;
use App\Enums\AgendaItemStatus;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\User;
use App\Support\Registers;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * بنود جدول أعمال الاجتماع وقراراتها وتكليفاتها (requirements/13 §2). يطّلع عليها من يرى الاجتماع،
 * ويديرها السكرتير في أي حال للاجتماع عبر SaveAgendaItem (MeetingAgendaItemPolicy).
 */
class AgendaItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'agendaItems';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('registers.agenda.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        /** @var Meeting $ownerRecord */
        return $user !== null && $ownerRecord->isVisibleTo($user);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('position')->label(__('registers.fields.position'))->integer()->minValue(1)->maxValue(999)->required()
                ->default(fn (): int => (int) MeetingAgendaItem::query()->where('meeting_id', $this->getOwnerRecord()->getKey())->max('position') + 1),
            Select::make('status')->label(__('registers.fields.execution_status'))->options(AgendaItemStatus::class)->default(AgendaItemStatus::NotStarted->value)->required(),
            TextInput::make('title')->label(__('registers.fields.item'))->required()->maxLength(300)->columnSpanFull(),
            Textarea::make('decision')->label(__('registers.fields.decision'))->rows(3)->maxLength(5000)->columnSpanFull(),
            Select::make('assignee_id')
                ->label(__('registers.fields.assignee'))
                ->options(fn (): array => Registers::usersWhoCan('meetings.view')->orderBy('name')->pluck('name', 'id')->all())
                ->searchable(),
            DatePicker::make('due_on')->label(__('registers.fields.due_on')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->modifyQueryUsing(fn ($query) => $query->with(['assignee', 'meeting']))
            ->columns([
                TextColumn::make('position')->label(__('registers.fields.position')),
                TextColumn::make('title')->label(__('registers.fields.item'))->wrap(),
                TextColumn::make('decision')->label(__('registers.fields.decision'))->wrap()->limit(120)->placeholder('—'),
                TextColumn::make('assignee.name')->label(__('registers.fields.assignee'))->placeholder('—'),
                TextColumn::make('due_on')->label(__('registers.fields.due_on'))->date('Y-m-d')->placeholder('—'),
                TextColumn::make('status')->label(__('registers.fields.execution_status'))->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('registers.fields.execution_status'))->options(AgendaItemStatus::class),
            ])
            ->headerActions([
                CreateAction::make()->using(fn (array $data): Model => $this->save(new MeetingAgendaItem, $data)),
            ])
            ->recordActions([
                EditAction::make()->using(fn (MeetingAgendaItem $record, array $data): Model => $this->save($record, $data)),
                DeleteAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(MeetingAgendaItem $item, array $data): MeetingAgendaItem
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var Meeting $meeting */
        $meeting = $this->getOwnerRecord();
        $data['status'] = $data['status'] instanceof AgendaItemStatus ? $data['status']->value : ($data['status'] ?? null);

        return app(SaveAgendaItem::class)->handle($actor, $meeting, $item, $data);
    }
}
