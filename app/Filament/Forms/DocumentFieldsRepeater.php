<?php

namespace App\Filament\Forms;

use App\Enums\DocumentFieldType;
use App\Support\DocumentFields;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

/**
 * حقول النموذج الإلكتروني التي يحددها السكرتير. ويعيد SaveFormTemplate تنظيفها عند الحفظ
 * (App\Support\DocumentFields::clean)، وتغييرها ينشئ إصداراً جديداً.
 */
final class DocumentFieldsRepeater
{
    public static function make(): Repeater
    {
        return Repeater::make('fields')
            ->label(__('documents.fields.fields'))
            ->helperText(__('documents.fields.fields_hint'))
            ->schema([
                TextInput::make('label')->label(__('documents.fields.field_label'))->required()->maxLength(150),
                TextInput::make('key')
                    ->label(__('documents.fields.field_key'))
                    ->helperText(__('documents.fields.field_key_hint'))
                    ->required()
                    ->maxLength(40)
                    ->regex('/^[a-z][a-z0-9_]*$/')
                    ->distinct()
                    ->extraInputAttributes(['dir' => 'ltr']),
                Select::make('type')->label(__('documents.fields.field_type'))->options(DocumentFieldType::class)->required()->live(),
                Toggle::make('required')->label(__('documents.fields.field_required'))->inline(false)
                    ->visible(fn (Get $get): bool => ! (self::type($get)?->isLayout() ?? false)),
                // حقلان متتاليان بنصف العرض صف من عمودين في التعبئة والطباعة، كالنموذج الورقي
                Select::make('width')
                    ->label(__('documents.fields.field_width'))
                    ->options(['full' => __('documents.widths.full'), 'half' => __('documents.widths.half')])
                    ->default('full')
                    ->selectablePlaceholder(false)
                    ->visible(fn (Get $get): bool => ($type = self::type($get)) !== null && ! $type->isLayout() && $type !== DocumentFieldType::Table),
                TagsInput::make('options')
                    ->label(__('documents.fields.field_options'))
                    ->visible(fn (Get $get): bool => self::type($get) === DocumentFieldType::Select)
                    ->required()
                    ->columnSpanFull(),
                TagsInput::make('columns')
                    ->label(__('documents.fields.field_columns'))
                    ->helperText(__('documents.fields.field_columns_hint'))
                    ->visible(fn (Get $get): bool => self::type($get) === DocumentFieldType::Table)
                    ->required()
                    ->reorderable()
                    ->columnSpanFull(),
                Textarea::make('text')
                    ->label(__('documents.fields.field_text'))
                    ->visible(fn (Get $get): bool => self::type($get) === DocumentFieldType::Paragraph)
                    ->required()
                    ->rows(3)
                    ->maxLength(DocumentFields::PARAGRAPH_MAX)
                    ->columnSpanFull(),
            ])
            ->columns(5)
            ->minItems(1)
            ->maxItems(DocumentFields::MAX_FIELDS)
            ->reorderable()
            ->collapsible()
            ->columnSpanFull();
    }

    /**
     * من اللوحة يصل التعداد نفسه أو قيمته.
     */
    private static function type(Get $get): ?DocumentFieldType
    {
        $type = $get('type');

        return $type instanceof DocumentFieldType ? $type : DocumentFieldType::tryFrom((string) $type);
    }
}
