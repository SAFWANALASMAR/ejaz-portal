<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\DocumentCategory;
use App\Filament\Forms\ContentFields;
use App\Models\Document;
use App\Rules\RealPdf;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('governance.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                ContentFields::slug(),
                Select::make('category')->label(__('governance.fields.category'))->options(DocumentCategory::class)->required(),
                DatePicker::make('issued_at')->label(__('governance.fields.issued_at')),
                TextInput::make('version')->label(__('governance.fields.version'))->maxLength(40),
                Textarea::make('description')->label(__('governance.fields.description'))->rows(3)->maxLength(1000)->columnSpanFull(),
                // في القرص الخاص، ويُقدَّم عبر مسار يفحص النشر (GovernanceController)
                FileUpload::make('file')
                    ->label(__('governance.fields.file'))
                    ->helperText(__('governance.fields.file_hint'))
                    ->required()
                    ->disk(Document::disk())
                    ->directory(Document::DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(20480)
                    ->rules([new RealPdf])
                    // لا رابط مؤقت موقّع: الملف يُنزَّل من إجراء «تنزيل الملف» بعد فحص الصلاحية
                    ->previewable(false)
                    ->columnSpanFull(),
                Toggle::make('is_published')->label(__('content.is_published')),
            ]),
        ]);
    }
}
