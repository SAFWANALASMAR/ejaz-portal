<?php

namespace App\Filament\Resources\AccountCards;

use App\Enums\AccountCardAudience;
use App\Enums\SiteIcon;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\AccountCards\Pages\CreateAccountCard;
use App\Filament\Resources\AccountCards\Pages\EditAccountCard;
use App\Filament\Resources\AccountCards\Pages\ListAccountCards;
use App\Models\AccountCard;
use App\Rules\PlainText;
use App\Rules\SafeUrl;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * بطاقات لوحة المستفيد (ملاحظات المدير التنفيذي، 25 أكتوبر 2026): الإضافة والتعديل والإخفاء والحذف والترتيب. وبطاقة القسم
 * المبني رابطها مساره فلا يُعدَّل، وتُخفى ولا تُحذف (AccountCardPolicy). الصلاحيات: account_cards.*.
 */
class AccountCardResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'account_cards';

    protected static string $navigationGroupKey = 'settings';

    protected static ?string $model = AccountCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 95;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('account_cards.fields.title'))->required()->maxLength(80)->rule(new PlainText),
                Select::make('icon')->label(__('account_cards.fields.icon'))->options(SiteIcon::class)->required()->native(false),
                TextInput::make('description')->label(__('account_cards.fields.description'))->maxLength(200)->rule(new PlainText)->columnSpanFull(),
                TextInput::make('url')
                    ->label(__('account_cards.fields.url'))
                    ->helperText(__('account_cards.fields.url_hint'))
                    ->maxLength(500)
                    ->rule(new SafeUrl)
                    ->required(fn (?AccountCard $record): bool => $record?->route === null)
                    ->hidden(fn (?AccountCard $record): bool => $record?->route !== null)
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->columnSpanFull(),
                Select::make('audience')->label(__('account_cards.fields.audience'))->options(AccountCardAudience::class)
                    ->default(AccountCardAudience::Everyone)->required()->native(false),
                Toggle::make('is_visible')->label(__('account_cards.fields.is_visible'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                IconColumn::make('icon')->label('')->icon(fn (AccountCard $record): string => 'heroicon-o-'.$record->icon->value),
                TextColumn::make('title')->label(__('account_cards.fields.title')),
                TextColumn::make('route')->label(__('account_cards.fields.kind'))
                    ->state(fn (AccountCard $record): string => __($record->route !== null ? 'account_cards.built_in' : 'account_cards.custom'))
                    ->badge(),
                TextColumn::make('audience')->label(__('account_cards.fields.audience'))->badge(),
                IconColumn::make('is_visible')->label(__('account_cards.fields.is_visible'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountCards::route('/'),
            'create' => CreateAccountCard::route('/create'),
            'edit' => EditAccountCard::route('/{record}/edit'),
        ];
    }
}
