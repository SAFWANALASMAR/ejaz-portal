<?php

namespace App\Filament\Resources\LiveStreams\Schemas;

use App\Enums\LiveStreamProvider;
use App\Enums\LiveStreamStatus;
use App\Models\LiveStream;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * نموذج البث: العنوان والمزود ورابطه والموعدان والنشر. ويُعاد فحص كل شيء في SaveLiveStream.
 */
class LiveStreamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('title')->label(__('livestream.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                Select::make('provider')->label(__('livestream.fields.provider'))->options(LiveStreamProvider::class)->required()->live(),
                TextInput::make('url')->label(__('livestream.fields.url'))
                    ->helperText(fn (Get $get): string => __($get('provider') === LiveStreamProvider::Teams->value ? 'livestream.fields.url_hint_teams' : 'livestream.fields.url_hint_youtube'))
                    ->required()
                    ->maxLength(500)
                    ->extraInputAttributes(['dir' => 'ltr']),
                Textarea::make('description')->label(__('livestream.fields.description'))->rows(4)->maxLength(2000)->columnSpanFull(),
                DateTimePicker::make('starts_at')->label(__('livestream.fields.starts_at'))->seconds(false)->required(),
                DateTimePicker::make('ends_at')->label(__('livestream.fields.ends_at'))->seconds(false)->required()->after('starts_at'),
                Toggle::make('is_published')->label(__('content.is_published')),
                TextEntry::make('status')
                    ->label(__('livestream.fields.status'))
                    ->badge()
                    ->state(fn (?LiveStream $record): ?LiveStreamStatus => $record?->exists ? $record->status() : null)
                    ->visible(fn (?LiveStream $record): bool => $record !== null && $record->exists),
            ]),
        ]);
    }
}
