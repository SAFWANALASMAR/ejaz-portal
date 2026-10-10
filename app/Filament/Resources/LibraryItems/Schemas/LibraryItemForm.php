<?php

namespace App\Filament\Resources\LibraryItems\Schemas;

use App\Enums\LibrarySection;
use App\Filament\Forms\ContentFields;
use App\Models\LibraryItem;
use App\Rules\RealPdf;
use App\Rules\VideoUrl;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * نموذج عنصر المكتبة: حقل المصدر يظهر حسب القسم، رابط المقطع للمرئية، وملف لما سواها. والمصدر
 * مطلوب عند النشر. ويُعاد فحص كل ذلك في SaveLibraryItem.
 */
class LibraryItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('library.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                ContentFields::slug(),
                Select::make('section')->label(__('library.fields.section'))->options(LibrarySection::class)->required()->live(),
                TextInput::make('author')->label(__('library.fields.author'))->maxLength(150),
                DatePicker::make('published_on')->label(__('library.fields.published_on')),
                TagsInput::make('tags')
                    ->label(__('library.fields.tags'))
                    ->helperText(__('library.fields.tags_hint'))
                    ->suggestions(fn (): array => LibraryItem::tagsIn(LibraryItem::query()))
                    ->rules(['array', 'max:'.LibraryItem::MAX_TAGS])
                    ->nestedRecursiveRules(['string', 'max:'.LibraryItem::MAX_TAG_LENGTH])
                    ->columnSpanFull(),
                Textarea::make('description')->label(__('library.fields.description'))->rows(4)->maxLength(2000)->columnSpanFull(),
                ContentFields::image('thumbnail', LibraryItem::IMAGE_DIRECTORY)
                    ->label(__('library.fields.thumbnail'))
                    ->helperText(__('content.image_hint'))
                    ->columnSpanFull(),
                TextInput::make('video_url')
                    ->label(__('library.fields.video_url'))
                    ->helperText(__('library.fields.video_url_hint'))
                    ->maxLength(VideoUrl::MAX_LENGTH)
                    ->rules([new VideoUrl])
                    ->required(fn (Get $get): bool => (bool) $get('is_published'))
                    ->visible(fn (Get $get): bool => self::section($get)?->takesVideoUrl() ?? false)
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->columnSpanFull(),
                // في القرص الخاص، ويُقدَّم عبر مسار يفحص النشر (LibraryController)
                FileUpload::make('file')
                    ->label(fn (Get $get): string => self::section($get)?->takesPdf() ? __('library.fields.pdf') : __('library.fields.audio'))
                    ->helperText(fn (Get $get): string => self::section($get)?->takesPdf() ? __('library.fields.pdf_hint') : __('library.fields.audio_hint'))
                    ->required(fn (Get $get): bool => (bool) $get('is_published'))
                    ->visible(fn (Get $get): bool => self::section($get) !== null && ! self::section($get)->takesVideoUrl())
                    ->disk(LibraryItem::disk())
                    ->directory(LibraryItem::FILE_DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(fn (Get $get): array => self::section($get)?->takesPdf() ? ['application/pdf'] : array_merge(...array_values(LibraryItem::AUDIO_TYPES)))
                    ->maxSize(fn (Get $get): int => self::section($get)?->takesPdf() ? 20480 : 25600)
                    ->rules(fn (Get $get): array => self::section($get)?->takesPdf() ? [new RealPdf] : [])
                    // لا رابط مؤقت موقّع: الملف يُنزَّل من إجراء «تنزيل الملف» بعد فحص الصلاحية
                    ->previewable(false)
                    ->columnSpanFull(),
                Toggle::make('is_published')->label(__('content.is_published'))->helperText(__('library.fields.publish_hint'))->live(),
                // نماذج الفيديو في الرئيسية (ملاحظة المدير التنفيذي): للمرئية برابط يوتيوب
                Toggle::make('on_home')->label(__('library.fields.on_home'))->helperText(__('library.fields.on_home_hint'))
                    ->visible(fn (Get $get): bool => self::section($get)?->takesVideoUrl() ?? false),
            ]),
        ]);
    }

    private static function section(Get $get): ?LibrarySection
    {
        $value = $get('section');

        return $value instanceof LibrarySection ? $value : LibrarySection::tryFrom((string) $value);
    }
}
