<?php

namespace App\Filament\Resources\StoreItems\Schemas;

use App\Enums\StoreItemCategory;
use App\Filament\Forms\ContentFields;
use App\Models\StoreItem;
use App\Rules\PlainText;
use App\Rules\StoreUrl;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * نموذج عنصر المتجر: العنوان والتصنيف والوصف القصير، والصورة لا SVG، ورابطه في سلة https من نطاقات المتجر المسموحة
 * (StoreUrl، ويعيد الإجراء فحصه)، ومعرّف المنتج في سلة اختيارياً للربط الآلي لاحقاً.
 */
class StoreItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('store.admin.fields.title'))->required()->maxLength(150)->rule(new PlainText),
                Select::make('category')->label(__('store.admin.fields.category'))->options(StoreItemCategory::class)
                    ->default(StoreItemCategory::Donation)->required()->native(false),
                Textarea::make('description')
                    ->label(__('store.admin.fields.description'))
                    ->helperText(__('store.admin.fields.description_hint'))
                    ->rows(3)
                    ->maxLength(500)
                    ->rule(new PlainText(multiline: true))
                    ->columnSpanFull(),
                TextInput::make('salla_url')
                    ->label(__('store.admin.fields.salla_url'))
                    ->helperText(__('store.admin.fields.salla_url_hint', ['hosts' => implode('، ', (array) config('store.allowed_hosts'))]))
                    ->required()
                    ->maxLength(500)
                    ->rule(new StoreUrl)
                    ->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('salla_product_id')
                    ->label(__('store.admin.fields.salla_product_id'))
                    ->helperText(__('store.admin.fields.salla_product_id_hint'))
                    ->maxLength(50)
                    ->regex('/^[A-Za-z0-9_-]+$/')
                    ->extraInputAttributes(['dir' => 'ltr']),
                ContentFields::image('image', StoreItem::IMAGE_DIRECTORY)
                    ->label(__('store.admin.fields.image'))
                    ->helperText(__('store.admin.fields.image_hint'))
                    ->columnSpanFull(),
                Toggle::make('is_published')->label(__('content.is_published')),
            ]),
        ]);
    }
}
