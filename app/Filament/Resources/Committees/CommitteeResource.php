<?php

namespace App\Filament\Resources\Committees;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\Committees\Pages\CreateCommittee;
use App\Filament\Resources\Committees\Pages\EditCommittee;
use App\Filament\Resources\Committees\Pages\ListCommittees;
use App\Models\Committee;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * لجان الجمعية بأسمائها كما تُدخلها الجمعية (requirements/13 §2). يديرها السكرتير، ويطّلع عليها المدير
 * التنفيذي والمجلس (committees.* — CommitteePolicy).
 */
class CommitteeResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'registers.committee';

    protected static string $navigationGroupKey = 'assembly';

    protected static ?string $model = Committee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                TextInput::make('name')->label(__('registers.fields.name'))->required()->maxLength(200),
                Textarea::make('description')->label(__('registers.fields.description'))->rows(3)->maxLength(2000),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('registers.fields.name'))->searchable()->wrap(),
                TextColumn::make('memberships_count')->label(__('registers.membership.plural'))->counts('memberships')->numeric(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toAction(array $data): array
    {
        return $data;
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommittees::route('/'),
            'create' => CreateCommittee::route('/create'),
            'edit' => EditCommittee::route('/{record}/edit'),
        ];
    }
}
