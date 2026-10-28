<?php

namespace App\Filament\Resources\Announcements\Schemas;

use App\Enums\AnnouncementKind;
use App\Filament\Forms\ContentFields;
use App\Models\Announcement;
use App\Rules\PlainText;
use App\Rules\SafeUrl;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * نموذج الإعلان: صورة لا SVG (docs/security.md)، وعنوان، ورابط https كامل أو مسار داخلي، أو المتجر من الإعدادات، ومدة
 * عرض بيومي البداية والنهاية.
 */
class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('announcements.fields.title'))->required()->maxLength(150)->rule(new PlainText)->columnSpanFull(),
                ContentFields::image('image', Announcement::IMAGE_DIRECTORY)
                    ->label(__('announcements.fields.image'))
                    ->helperText(__('announcements.fields.image_hint'))
                    ->required()
                    ->columnSpanFull(),
                Select::make('kind')
                    ->label(__('announcements.fields.kind'))
                    ->helperText(__('announcements.fields.kind_hint'))
                    ->options(AnnouncementKind::class)
                    ->default(AnnouncementKind::General)
                    ->required()
                    ->native(false),
                TextInput::make('url')
                    ->label(__('announcements.fields.url'))
                    ->helperText(__('announcements.fields.url_hint'))
                    ->maxLength(500)
                    ->rule(new SafeUrl)
                    ->extraInputAttributes(['dir' => 'ltr']),
                DatePicker::make('starts_on')->label(__('announcements.fields.starts_on'))->native(false),
                DatePicker::make('ends_on')->label(__('announcements.fields.ends_on'))->native(false)->afterOrEqual('starts_on'),
                Toggle::make('is_published')->label(__('content.is_published')),
            ]),
        ]);
    }
}
