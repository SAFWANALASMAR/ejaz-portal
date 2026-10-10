<?php

namespace App\Filament\Resources\ProgramTypes;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Forms\ContentFields;
use App\Filament\Resources\ProgramTypes\Pages\ManageProgramTypes;
use App\Models\ProgramType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * أنواع البرامج: تبويبات «برامجنا». الصلاحيات: program_types.* — ProgramTypePolicy. ولا يُحذف
 * نوع له برامج.
 */
class ProgramTypeResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'programs.types';

    protected static string $navigationGroupKey = 'programs';

    protected static ?string $model = ProgramType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('programs.types.fields.name'))->required()->maxLength(100),
            ContentFields::slug(),
            TextInput::make('sort')->label(__('programs.types.fields.sort'))->integer()->minValue(0)->maxValue(1000)->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->modifyQueryUsing(fn ($query) => $query->withCount('programs'))
            ->columns([
                TextColumn::make('name')->label(__('programs.types.fields.name')),
                TextColumn::make('slug')->label(__('content.slug')),
                TextColumn::make('sort')->label(__('programs.types.fields.sort'))->sortable(),
                TextColumn::make('programs_count')->label(__('programs.types.fields.programs_count'))->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (ProgramType $record): bool => $record->programs()->withTrashed()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProgramTypes::route('/'),
        ];
    }
}
