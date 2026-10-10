<?php

namespace App\Filament\Resources\Partners\Schemas;

use App\Filament\Forms\ContentFields;
use App\Models\Partner;
use App\Rules\SafeUrl;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * نموذج الشريك: الشعار صورة لا SVG (docs/security.md)، والرابط https كاملاً.
 */
class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('partners.fields.name'))->required()->maxLength(150),
                TextInput::make('url')
                    ->label(__('partners.fields.url'))
                    ->helperText(__('partners.fields.url_hint'))
                    ->maxLength(500)
                    ->rule(new SafeUrl(allowInternal: false))
                    ->extraInputAttributes(['dir' => 'ltr']),
                ContentFields::image('logo', Partner::IMAGE_DIRECTORY)
                    ->label(__('partners.fields.logo'))
                    ->helperText(__('partners.fields.logo_hint'))
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_published')->label(__('content.is_published')),
            ]),
        ]);
    }
}
