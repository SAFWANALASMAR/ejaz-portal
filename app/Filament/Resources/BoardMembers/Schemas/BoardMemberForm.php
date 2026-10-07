<?php

namespace App\Filament\Resources\BoardMembers\Schemas;

use App\Filament\Forms\ContentFields;
use App\Models\BoardMember;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BoardMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->description(__('board.consent_hint'))->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('board.fields.name'))->required()->maxLength(160),
                TextInput::make('position')->label(__('board.fields.position'))->required()->maxLength(160),
                ContentFields::image('photo', BoardMember::IMAGE_DIRECTORY)
                    ->label(__('board.fields.photo'))
                    ->helperText(__('content.image_hint'))
                    ->avatar(),
                Toggle::make('is_visible')->label(__('board.fields.is_visible'))->default(true),
            ]),
        ]);
    }
}
