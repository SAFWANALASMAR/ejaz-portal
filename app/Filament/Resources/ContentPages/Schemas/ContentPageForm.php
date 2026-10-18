<?php

namespace App\Filament\Resources\ContentPages\Schemas;

use App\Filament\Forms\ContentFields;
use App\Models\Page;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContentPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('pages.fields.title'))->required()->maxLength(160),
                // معرّفا «عن الجمعية» و«سياسة الخصوصية» ثابتان لأن رابطيهما مبنيان عليهما (SaveContent يمنعه أيضاً)
                ContentFields::slug()->disabled(fn (?Page $record): bool => in_array($record?->slug, Page::LOCKED_SLUGS, true)),
                Textarea::make('summary')->label(__('pages.fields.summary'))->helperText(__('pages.fields.summary_hint'))->rows(2)->maxLength(300)->columnSpanFull(),
                Toggle::make('is_published')->label(__('content.is_published')),
            ]),
            ContentFields::blocks(Page::IMAGE_DIRECTORY),
        ]);
    }
}
