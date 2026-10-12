<?php

namespace App\Filament\Resources\PlatformAccounts;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\PlatformAccounts\Pages\CreatePlatformAccount;
use App\Filament\Resources\PlatformAccounts\Pages\EditPlatformAccount;
use App\Filament\Resources\PlatformAccounts\Pages\ListPlatformAccounts;
use App\Filament\Resources\PlatformAccounts\Pages\ViewPlatformAccount;
use App\Models\PlatformAccount;
use App\Support\Registers;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
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
 * حسابات المنصات بلا كلمات مرور (requirements/13 §4، والقرار في decisions.md). للمدير التنفيذي والسكرتير
 * (platform_accounts.* — PlatformAccountPolicy)، وفتح صفحة الحساب يُسجَّل.
 */
class PlatformAccountResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'registers.platform';

    protected static string $navigationGroupKey = 'registers';

    protected static ?string $model = PlatformAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 3;

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('responsible');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->description(__('registers.platform.no_passwords'))->columns(2)->columnSpanFull()->schema([
                TextInput::make('platform')->label(__('registers.fields.platform'))->required()->maxLength(200),
                Select::make('responsible_id')
                    ->label(__('registers.fields.responsible'))
                    ->options(fn (): array => Registers::usersWhoCan('platform_accounts.view')->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                TextInput::make('username')->label(__('registers.fields.username'))->maxLength(200),
                TextInput::make('login_url')->label(__('registers.fields.login_url'))->url()->maxLength(500)->placeholder('https://'),
                Textarea::make('notes')->label(__('registers.fields.notes'))->rows(3)->maxLength(2000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->description(__('registers.platform.no_passwords'))->columns(2)->columnSpanFull()->schema([
                TextEntry::make('platform')->label(__('registers.fields.platform')),
                TextEntry::make('responsible.name')->label(__('registers.fields.responsible'))->placeholder('—'),
                TextEntry::make('username')->label(__('registers.fields.username'))->placeholder('—')->copyable(),
                TextEntry::make('login_url')->label(__('registers.fields.login_url'))->placeholder('—')->url(fn (PlatformAccount $record): ?string => $record->login_url, shouldOpenInNewTab: true),
                TextEntry::make('notes')->label(__('registers.fields.notes'))->placeholder('—')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('platform')
            ->columns([
                TextColumn::make('platform')->label(__('registers.fields.platform'))->searchable(),
                TextColumn::make('username')->label(__('registers.fields.username'))->placeholder('—'),
                TextColumn::make('responsible.name')->label(__('registers.fields.responsible'))->placeholder('—'),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([
                ViewAction::make(),
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
            'index' => ListPlatformAccounts::route('/'),
            'create' => CreatePlatformAccount::route('/create'),
            'view' => ViewPlatformAccount::route('/{record}'),
            'edit' => EditPlatformAccount::route('/{record}/edit'),
        ];
    }
}
