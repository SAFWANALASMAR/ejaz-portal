<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use App\Models\MenuItem;
use App\Rules\SafeUrl;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('label')
                            ->label(__('menu.fields.label'))
                            ->required()
                            ->maxLength(100),
                        TextInput::make('url')
                            ->label(__('menu.fields.url'))
                            ->helperText(__('menu.fields.url_hint'))
                            ->required()
                            ->maxLength(500)
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->rule(new SafeUrl),
                        Select::make('parent_id')
                            ->label(__('menu.fields.parent'))
                            ->placeholder(__('menu.fields.parent_none'))
                            // مستويان فقط: الأب عنصر رئيسي، ولا يكون العنصر أباً لنفسه
                            ->options(fn (?MenuItem $record) => MenuItem::query()
                                ->whereNull('parent_id')
                                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->orderBy('sort')
                                ->pluck('label', 'id'))
                            ->rule(fn (?MenuItem $record) => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                                // عنصر له فروع لا يصير فرعاً، فتبقى القائمة بمستويين
                                if (filled($value) && $record?->children()->exists()) {
                                    $fail(__('menu.errors.has_children'));
                                }
                            })
                            ->searchable(),
                        TextInput::make('sort')
                            ->label(__('menu.fields.sort'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->default(0),
                        Toggle::make('is_visible')
                            ->label(__('menu.fields.is_visible'))
                            ->default(true),
                        Toggle::make('opens_in_new_tab')
                            ->label(__('menu.fields.opens_in_new_tab')),
                    ]),
            ]);
    }
}
