<?php

namespace App\Filament\Resources\NewsArticles\Tables;

use App\Filament\Actions\DeleteContentAction;
use App\Models\NewsArticle;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query->scopes('newestFirst'))
            ->columns([
                ImageColumn::make('image')->label('')->disk('public'),
                TextColumn::make('title')->label(__('news.fields.title'))->searchable()->wrap(),
                TextColumn::make('category')->label(__('news.fields.category'))->badge(),
                TextColumn::make('published_on')->label(__('news.fields.published_on'))->date('Y-m-d')->sortable(),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('news.fields.category'))
                    ->options(fn (): array => collect(NewsArticle::categoriesIn(NewsArticle::query()))
                        ->mapWithKeys(fn (string $category): array => [$category => $category])
                        ->all()),
                TernaryFilter::make('is_published')->label(__('content.is_published')),
            ])
            // حذف فردي فقط، حتى يمر كل حذف بالإجراء (الصور، وسجل العمليات، والذاكرة)
            ->recordActions([
                EditAction::make(),
                DeleteContentAction::make(),
            ]);
    }
}
