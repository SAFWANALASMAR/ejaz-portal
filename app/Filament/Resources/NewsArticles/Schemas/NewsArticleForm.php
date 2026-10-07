<?php

namespace App\Filament\Resources\NewsArticles\Schemas;

use App\Filament\Forms\ContentFields;
use App\Models\NewsArticle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * نموذج الخبر: محتواه بمحرر المقاطع نفسه، والتصنيف نص حر باقتراح المستعمل.
 */
class NewsArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('news.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                ContentFields::slug(),
                DatePicker::make('published_on')->label(__('news.fields.published_on'))->required()->default(now()),
                TextInput::make('category')
                    ->label(__('news.fields.category'))
                    ->helperText(__('news.fields.category_hint'))
                    ->maxLength(60)
                    ->datalist(fn (): array => NewsArticle::categoriesIn(NewsArticle::query())),
                Toggle::make('is_published')->label(__('content.is_published')),
                Textarea::make('summary')->label(__('news.fields.summary'))->rows(2)->maxLength(300)->columnSpanFull(),
                ContentFields::image('image', NewsArticle::IMAGE_DIRECTORY)
                    ->label(__('news.fields.image'))
                    ->helperText(__('content.image_hint'))
                    ->columnSpanFull(),
            ]),
            ContentFields::blocks(NewsArticle::IMAGE_DIRECTORY),
            Section::make(__('news.fields.gallery'))->columnSpanFull()->schema([
                ContentFields::image('gallery', NewsArticle::IMAGE_DIRECTORY)
                    ->hiddenLabel()
                    ->helperText(__('content.image_hint'))
                    ->multiple()
                    ->reorderable()
                    ->maxFiles(12),
            ]),
        ]);
    }
}
