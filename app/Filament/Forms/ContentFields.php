<?php

namespace App\Filament\Forms;

use App\Enums\SiteIcon;
use App\Support\RichText;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

/**
 * حقول نماذج المحتوى في اللوحة: محرر المقاطع المرنة، ورفع الصور، والمعرّف في الرابط.
 */
final class ContentFields
{
    /**
     * محرر المقاطع (requirements: «محرر محتوى مرن»): نص منسق، وبطاقات، وصورة. يُعرض في
     * الموقع بـ x-content-blocks، والنص المنسق بعد تنقيته (App\Support\RichText).
     *
     * و$editorImages لـ«محرر الصفحات» (SitePageResource): مفتاح إظهار لكل مقطع، وصور المقاطع المقبولة بلا رفع هي صور الصفحة
     * ونسخها (EditablePages::knownImages)، لأن المسودة تحمل صوراً ليست في سجل الصفحة.
     *
     * @param  Closure|null  $editorImages  يقبل المسار ($file) ويُحقن معه السجل ($record)
     */
    public static function blocks(string $directory, ?Closure $editorImages = null): Builder
    {
        $picture = function (string $name) use ($directory, $editorImages): FileUpload {
            $upload = self::image($name, $directory, BlockPictureUpload::class);

            return $editorImages === null ? $upload : $upload->preventFilePathTampering(allowFilePathUsing: $editorImages);
        };
        $visible = fn (): array => $editorImages === null ? [] : [
            Toggle::make('visible')->label(__('page_editor.fields.visible'))->default(true)->inline(false),
        ];

        return Builder::make('blocks')
            ->label(__('content.blocks.label'))
            ->blocks([
                Block::make('text')
                    ->label(__('content.blocks.text'))
                    ->icon(Heroicon::OutlinedBars3BottomRight)
                    ->schema([
                        ...$visible(),
                        TextInput::make('heading')->label(__('content.blocks.heading'))->maxLength(160),
                        // أدوات النص الأساسية وحدها، وهي ما يبقى بعد التنقية في الموقع
                        RichEditor::make('body')
                            ->label(__('content.blocks.body'))
                            ->helperText(__('content.blocks.body_hint'))
                            ->required()
                            ->maxLength(RichText::MAX_LENGTH)
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['h2', 'h3'],
                                ['bulletList', 'orderedList', 'blockquote'],
                                ['undo', 'redo'],
                            ]),
                    ]),
                Block::make('cards')
                    ->label(__('content.blocks.cards'))
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->schema([
                        ...$visible(),
                        TextInput::make('heading')->label(__('content.blocks.heading'))->maxLength(160),
                        Repeater::make('items')
                            ->label(__('content.blocks.items'))
                            ->schema([
                                Select::make('icon')->label(__('content.blocks.icon'))->options(SiteIcon::class),
                                TextInput::make('title')->label(__('content.blocks.card_title'))->required()->maxLength(120),
                                Textarea::make('text')->label(__('content.blocks.card_text'))->rows(3)->maxLength(600),
                                // صورة بدل الأيقونة، من صور مقاطع السجل نفسه (BlockPictureUpload)
                                $picture('picture')
                                    ->label(__('content.blocks.card_picture'))
                                    ->helperText(__('content.blocks.card_picture_hint'))
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->minItems(1)
                            ->maxItems(12),
                    ]),
                Block::make('image')
                    ->label(__('content.blocks.image'))
                    ->icon(Heroicon::OutlinedPhoto)
                    ->schema([
                        ...$visible(),
                        // المحفوظ صور مقاطع السجل نفسه، لا غلاف المبادرة (BlockPictureUpload)
                        $picture('picture')
                            ->label(__('content.blocks.image'))
                            ->helperText(__('content.image_hint'))
                            ->required(),
                        TextInput::make('alt')->label(__('content.blocks.alt'))->helperText(__('content.blocks.alt_hint'))->maxLength(300),
                        TextInput::make('caption')->label(__('content.blocks.caption'))->maxLength(200),
                    ]),
            ])
            ->maxItems(30)
            ->collapsible()
            ->blockNumbers(false)
            ->columnSpanFull();
    }

    /**
     * رفع صورة على القرص العام: JPG أو PNG أو WebP، ولا SVG (docs/security.md). وتُعاد
     * ترميزها قبل النشر داخل طلب الحفظ، فتُحدّ أبعادها بما يكفي للموقع ولا يثقل الحفظ.
     * ومنع التلاعب بالمسار مفعّل لكل حقول الرفع في AdminPanelProvider.
     */
    /**
     * @param  class-string<FileUpload>  $component
     */
    public static function image(string $name, string $directory, string $component = FileUpload::class): FileUpload
    {
        return $component::make($name)
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(4096)
            ->rules(['dimensions:max_width=3000,max_height=3000'])
            ->disk('public')
            ->directory($directory)
            ->visibility('public');
    }

    /**
     * المعرّف في الرابط: حروف لاتينية صغيرة وأرقام وشرطات.
     */
    public static function slug(): TextInput
    {
        return TextInput::make('slug')
            ->label(__('content.slug'))
            ->helperText(__('content.slug_hint'))
            ->required()
            ->maxLength(80)
            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
            ->validationMessages(['regex' => __('content.slug_format')])
            ->unique(ignoreRecord: true)
            ->extraInputAttributes(['dir' => 'ltr']);
    }
}
