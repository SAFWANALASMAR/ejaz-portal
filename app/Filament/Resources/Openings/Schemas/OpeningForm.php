<?php

namespace App\Filament\Resources\Openings\Schemas;

use App\Enums\OpeningKind;
use App\Filament\Forms\ContentFields;
use App\Filament\Forms\FormFieldsRepeater;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OpeningForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('services.openings.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                ContentFields::slug(),
                Select::make('kind')->label(__('services.openings.fields.kind'))->options(OpeningKind::class)->required(),
                Textarea::make('summary')->label(__('services.openings.fields.summary'))->rows(2)->maxLength(500)->columnSpanFull(),
                Textarea::make('description')->label(__('services.openings.fields.description'))->rows(8)->maxLength(10000)->columnSpanFull(),
                DateTimePicker::make('closes_at')->label(__('services.openings.fields.closes_at'))->helperText(__('services.openings.fields.closes_at_hint'))->seconds(false),
                Toggle::make('is_published')->label(__('content.is_published')),
            ]),
            Section::make(__('services.openings.fields.form_fields'))
                ->description(__('services.openings.fields.form_fields_hint'))
                ->columnSpanFull()
                ->schema([
                    FormFieldsRepeater::make(),
                ]),
        ]);
    }
}
