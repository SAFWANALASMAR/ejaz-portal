<?php

namespace App\Filament\Resources\Redirects\Schemas;

use App\Enums\RedirectType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * الحقول وحدودها، وفحوص المصدر والوجهة كلها في SaveRedirect، فتسري على الاستيراد أيضاً.
 */
class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('source')
                            ->label(__('redirects.fields.source'))
                            ->helperText(__('redirects.fields.source_hint'))
                            ->required()
                            ->maxLength(1000)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('target')
                            ->label(__('redirects.fields.target'))
                            ->helperText(__('redirects.fields.target_hint'))
                            ->required()
                            ->maxLength(500)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        Select::make('type')
                            ->label(__('redirects.fields.type'))
                            ->helperText(__('redirects.fields.type_hint'))
                            ->options(RedirectType::class)
                            ->default(RedirectType::Permanent)
                            ->required(),
                        Toggle::make('is_active')
                            ->label(__('redirects.fields.is_active'))
                            ->default(true),
                    ]),
            ]);
    }
}
