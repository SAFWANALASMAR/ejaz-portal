<?php

namespace App\Filament\Resources\SocialPosts\Tables;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use App\Filament\Actions\DeleteContentAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SocialPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('posted_on', 'desc')
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->disabledClick(),
                TextColumn::make('body')->label(__('social_posts.fields.body'))->limit(70)->wrap()->searchable(),
                TextColumn::make('platform')->label(__('social_posts.fields.platform'))->badge(),
                TextColumn::make('kind')->label(__('social_posts.fields.kind'))->badge(),
                TextColumn::make('posted_on')->label(__('social_posts.fields.posted_on'))->date('Y-m-d')->sortable(),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('platform')->label(__('social_posts.fields.platform'))->options(SocialPlatform::class),
                SelectFilter::make('kind')->label(__('social_posts.fields.kind'))->options(SocialPostKind::class),
                TernaryFilter::make('is_published')->label(__('content.is_published')),
            ])
            // حذف فردي فقط، حتى يمر كل حذف بالإجراء (الصورة، وسجل العمليات، والذاكرة)
            ->recordActions([
                EditAction::make(),
                DeleteContentAction::make(),
            ]);
    }
}
