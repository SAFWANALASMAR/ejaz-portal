<?php

namespace App\Filament\Resources\Albums\Schemas;

use App\Filament\Forms\ContentFields;
use App\Models\Album;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * نموذج الألبوم: صوره بترتيبها، وأولها غلافه. ولا يُنشر ألبوم بلا صور.
 */
class AlbumForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('albums.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                ContentFields::slug(),
                DatePicker::make('published_on')->label(__('albums.fields.published_on'))->required()->default(now()),
                Textarea::make('description')->label(__('albums.fields.description'))->rows(3)->maxLength(1000)->columnSpanFull(),
                Toggle::make('is_published')->label(__('content.is_published'))->live(),
            ]),
            Section::make(__('albums.fields.photos'))->columnSpanFull()->schema([
                ContentFields::image('photos', Album::IMAGE_DIRECTORY)
                    ->label(__('albums.fields.photos'))->hiddenLabel()
                    ->helperText(__('albums.fields.photos_hint'))
                    ->multiple()
                    ->reorderable()
                    ->panelLayout('grid')
                    ->maxFiles(Album::MAX_PHOTOS)
                    ->required(fn (Get $get): bool => (bool) $get('is_published')),
            ]),
        ]);
    }
}
