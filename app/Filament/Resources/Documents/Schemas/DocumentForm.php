<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\DocumentCategory;
use App\Filament\Forms\ContentFields;
use App\Models\Document;
use App\Rules\RealPdf;
use App\Rules\SafeUrl;
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
                    // الملف أو الرابط، أحدهما وحده، ويفحصه SaveDocument أيضاً
                    ->requiredWithout('url')
                    ->prohibits('url')
                    ->validationMessages(['required_without' => __('governance.errors.file_or_url'), 'prohibits' => __('governance.errors.file_and_url')])
                    ->disk(Document::disk())
                    ->directory(Document::DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(20480)
                    ->rules([new RealPdf])
                    // لا رابط مؤقت موقّع: الملف يُنزَّل من إجراء «تنزيل الملف» بعد فحص الصلاحية
                    ->previewable(false)
                    ->columnSpanFull(),
                // بديل الملف: يُفتح في تبويب جديد، ولا يُضمَّن في الصفحة
                TextInput::make('url')
                    ->label(__('governance.fields.url'))
                    ->helperText(__('governance.fields.url_hint'))
                    ->requiredWithout('file')
                    ->validationMessages(['required_without' => __('governance.errors.file_or_url')])
                    ->maxLength(2048)
                    ->rules([new SafeUrl(allowInternal: false)])
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->columnSpanFull(),
                // «للاطلاع فقط» افتراضاً (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٢٢): قارئ الصفحة وحده بلا تنزيل، ويُطفأ لوثيقة
                // بعينها متى قررت الجمعية فتح تنزيلها. ولا أثر له في وثيقة الرابط المباشر
                Toggle::make('is_view_only')->label(__('governance.view_only.field'))->helperText(__('governance.view_only.field_hint'))
                    ->default(true)->columnSpanFull(),
                Toggle::make('is_published')->label(__('content.is_published')),
            ]),
        ]);
    }
}
