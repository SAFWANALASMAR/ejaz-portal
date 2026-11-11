<?php

namespace App\Filament\Resources\Initiatives\Schemas;

use App\Filament\Forms\ContentFields;
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
                ContentFields::slug(),
                Textarea::make('summary')->label(__('initiatives.fields.summary'))->rows(2)->maxLength(300)->columnSpanFull(),
                ContentFields::image('image', Initiative::IMAGE_DIRECTORY)
                    ->label(__('initiatives.fields.image'))
                    ->helperText(__('content.image_hint'))
                    ->columnSpanFull(),
                Toggle::make('is_published')->label(__('content.is_published')),
            ]),
            Section::make(__('initiatives.fields.goals'))->columnSpanFull()->schema([
                // التسمية مخفية، فزر الإضافة باسمه العربي لا «إضافة إلى goals» من اسم الحقل (فحص 11 نوفمبر 2026)
                Repeater::make('goals')
                    ->label(__('initiatives.fields.goals'))
                    ->hiddenLabel()
                    ->addActionLabel(__('initiatives.fields.add_goal'))
                    ->simple(TextInput::make('goal')->label(__('initiatives.fields.goal'))->required()->maxLength(300))
                    ->defaultItems(0)
                    ->maxItems(12),
            ]),
            ContentFields::blocks(Initiative::IMAGE_DIRECTORY),
            Section::make(__('initiatives.fields.gallery'))->columnSpanFull()->schema([
                ContentFields::image('gallery', Initiative::IMAGE_DIRECTORY)
                    ->hiddenLabel()
                    ->helperText(__('content.image_hint'))
                    ->multiple()
                    ->reorderable()
                    ->maxFiles(8),
            ]),
        ]);
    }
}
