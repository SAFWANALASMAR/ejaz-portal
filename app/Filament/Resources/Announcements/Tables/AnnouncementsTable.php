<?php

namespace App\Filament\Resources\Announcements\Tables;

use App\Actions\Content\RecordContentReorder;
use App\Enums\AnnouncementKind;
use App\Filament\Actions\DeleteContentAction;
use App\Models\Announcement;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            // الترتيب يُحفظ باستعلام واحد لا يمر بأحداث النموذج، فيُسجَّل ويُمسح التخزين بإجراء
            ->afterReordering(fn (array $order) => app(RecordContentReorder::class)->handle(Announcement::class, $order))
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->disabledClick(),
                TextColumn::make('title')->label(__('announcements.fields.title'))->searchable(),
                TextColumn::make('kind')->label(__('announcements.fields.kind'))->badge(),
                TextColumn::make('starts_on')->label(__('announcements.fields.starts_on'))->date('Y-m-d')->placeholder('—'),
                TextColumn::make('ends_on')->label(__('announcements.fields.ends_on'))->date('Y-m-d')->placeholder('—'),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('kind')->label(__('announcements.fields.kind'))->options(AnnouncementKind::class),
            ])
            // حذف فردي فقط، حتى يمر كل حذف بالإجراء (الصورة، وسجل العمليات، والذاكرة)
            ->recordActions([
                EditAction::make(),
                DeleteContentAction::make(),
            ]);
    }
}
