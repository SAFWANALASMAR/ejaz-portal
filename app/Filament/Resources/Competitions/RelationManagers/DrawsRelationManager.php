<?php

namespace App\Filament\Resources\Competitions\RelationManagers;

use App\Actions\Competitions\RunDraw;
use App\Models\Competition;
use App\Models\CompetitionDraw;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * سجل السحب الإلكتروني للمسابقة: يطّلع عليه من يطّلع على المسابقات، ويُنفّذ منه السحب لمن له
 * competitions.draw. والسجل ثابت: لا تعديل ولا حذف (CompetitionDrawPolicy ومشغّل قاعدة البيانات).
 */
class DrawsRelationManager extends RelationManager
{
    protected static string $relationship = 'draws';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('competitions.draws.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewAny', CompetitionDraw::class) ?? false;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('executed_at')->label(__('competitions.draws.executed_at'))->dateTime('Y-m-d H:i:s'),
            TextEntry::make('executor.name')->label(__('competitions.draws.executor')),
            TextEntry::make('entrants_count')->label(__('competitions.draws.entrants_count')),
            TextEntry::make('entrants_hash')->label(__('competitions.draws.entrants_hash'))->copyable()->extraAttributes(['dir' => 'ltr']),
            TextEntry::make('seed')->label(__('competitions.draws.seed'))->copyable()->extraAttributes(['dir' => 'ltr']),
            TextEntry::make('uuid')->label(__('competitions.draws.uuid'))->copyable()->extraAttributes(['dir' => 'ltr']),
            TextEntry::make('winners')->label(__('competitions.draws.winners'))->listWithLineBreaks()->bulleted(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('executor'))
            ->columns([
                TextColumn::make('executed_at')->label(__('competitions.draws.executed_at'))->dateTime('Y-m-d H:i'),
                TextColumn::make('executor.name')->label(__('competitions.draws.executor')),
                TextColumn::make('entrants_count')->label(__('competitions.draws.entrants_count'))->numeric(),
                TextColumn::make('winners_count')->label(__('competitions.draws.winners_count'))->numeric(),
                TextColumn::make('entrants_hash')->label(__('competitions.draws.entrants_hash'))->limit(16),
            ])
            ->headerActions([
                Action::make('draw')
                    ->label(__('competitions.draws.run'))
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->visible(fn (): bool => auth()->user()?->can('competitions.draw') ?? false)
                    ->modalDescription(__('competitions.draws.run_confirm'))
                    ->schema([
                        TextInput::make('winners_count')->label(__('competitions.draws.winners_count'))->integer()->minValue(1)->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var User $user */
                        $user = auth()->user();
                        /** @var Competition $competition */
                        $competition = $this->getOwnerRecord();

                        try {
                            app(RunDraw::class)->handle($user, $competition, (int) $data['winners_count']);
                            Notification::make()->success()->title(__('competitions.draws.done'))->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                        }
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
