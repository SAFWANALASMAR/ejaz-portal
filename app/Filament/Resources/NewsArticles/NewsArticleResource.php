<?php

namespace App\Filament\Resources\NewsArticles;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\NewsArticles\Pages\CreateNewsArticle;
use App\Filament\Resources\NewsArticles\Pages\EditNewsArticle;
use App\Filament\Resources\NewsArticles\Pages\ListNewsArticles;
use App\Filament\Resources\NewsArticles\Schemas\NewsArticleForm;
use App\Filament\Resources\NewsArticles\Tables\NewsArticlesTable;
use App\Models\NewsArticle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * الأخبار. الصلاحيات: news.* — NewsArticlePolicy.
 */
class NewsArticleResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'news';

    protected static string $navigationGroupKey = 'media';

    protected static ?string $model = NewsArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return NewsArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsArticlesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsArticles::route('/'),
            'create' => CreateNewsArticle::route('/create'),
            'edit' => EditNewsArticle::route('/{record}/edit'),
        ];
    }
}
