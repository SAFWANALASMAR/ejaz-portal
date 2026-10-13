<?php

namespace App\Filament\Forms;

use App\Enums\FormFieldType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

/**
 * حقول النموذج التي يحددها المسؤول (الفرص، والبرامج). ويعيد الإجراء تنظيفها عند الحفظ
 * (App\Support\FormFieldRules::clean).
 */
final class FormFieldsRepeater
{
    public static function make(bool $allowFiles = true): Repeater
    {
        $types = collect(FormFieldType::cases())
            ->reject(fn (FormFieldType $type): bool => $type === FormFieldType::Pdf && ! $allowFiles)
            ->mapWithKeys(fn (FormFieldType $type): array => [$type->value => $type->getLabel()]);

        return Repeater::make('form_fields')
            ->hiddenLabel()
            ->schema([
                TextInput::make('label')->label(__('services.openings.fields.field_label'))->required()->maxLength(150),
                TextInput::make('key')
                    ->label(__('services.openings.fields.field_key'))
                    ->helperText(__('services.openings.fields.field_key_hint'))
                    ->required()
                    ->maxLength(40)
                    ->regex('/^[a-z][a-z0-9_]*$/')
                    ->distinct()
                    ->extraInputAttributes(['dir' => 'ltr']),
                Select::make('type')->label(__('services.openings.fields.field_type'))->options($types)->required()->live(),
                Toggle::make('required')->label(__('services.openings.fields.field_required'))->inline(false),
                TagsInput::make('options')
                    ->label(__('services.openings.fields.field_options'))
                    ->visible(fn (Get $get): bool => $get('type') === FormFieldType::Select || $get('type') === FormFieldType::Select->value)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->columns(4)
            ->maxItems(40)
            ->reorderable()
            ->collapsible()
            ->defaultItems(0);
    }
}
