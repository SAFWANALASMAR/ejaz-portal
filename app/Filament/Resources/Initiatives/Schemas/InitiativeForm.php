<?php

namespace App\Filament\Resources\Initiatives\Schemas;

use App\Filament\Forms\ContentBlocksField;
use App\Models\Initiative;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InitiativeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('initiatives.fields.name'))->required()->maxLength(120),
                ContentBlocksField::slug(),
                Textarea::make('summary')->label(__('initiatives.fields.summary'))->rows(2)->maxLength(300)->columnSpanFull(),
                ContentBlocksField::image('image', Initiative::IMAGE_DIRECTORY)
                    ->label(__('initiatives.fields.image'))
                    ->helperText(__('content.image_hint'))
                    ->columnSpanFull(),
                Toggle::make('is_published')->label(__('content.is_published')),
            ]),
            Section::make(__('initiatives.fields.goals'))->columnSpanFull()->schema([
                Repeater::make('goals')
                    ->hiddenLabel()
                    ->simple(TextInput::make('goal')->label(__('initiatives.fields.goal'))->required()->maxLength(300))
                    ->defaultItems(0)
                    ->maxItems(12),
            ]),
            ContentBlocksField::make(Initiative::IMAGE_DIRECTORY),
            Section::make(__('initiatives.fields.gallery'))->columnSpanFull()->schema([
                ContentBlocksField::image('gallery', Initiative::IMAGE_DIRECTORY)
                    ->hiddenLabel()
                    ->helperText(__('content.image_hint'))
                    ->multiple()
                    ->reorderable()
                    ->maxFiles(12),
            ]),
        ]);
    }
}
