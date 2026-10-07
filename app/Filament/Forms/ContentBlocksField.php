<?php

namespace App\Filament\Forms;

use App\Enums\SiteIcon;
use App\Models\Contracts\HasPublicImages;
use App\Support\ContentBlocks;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * محرر المقاطع المرنة (requirements: «محرر محتوى مرن»): نص منسق، وبطاقات، وصورة.
 * يُعرض في الموقع بـ x-content-blocks، والنص المنسق بعد تنقيته (App\Support\RichText).
 */
final class ContentBlocksField
{
    public static function make(string $directory): Builder
    {
        return Builder::make('blocks')
            ->label(__('content.blocks.label'))
            ->blocks([
                Block::make('text')
                    ->label(__('content.blocks.text'))
                    ->icon(Heroicon::OutlinedBars3BottomRight)
                    ->schema([
                        TextInput::make('heading')->label(__('content.blocks.heading'))->maxLength(160),
                        // أدوات النص الأساسية وحدها، وهي ما يبقى بعد التنقية في الموقع
                        RichEditor::make('body')
                            ->label(__('content.blocks.body'))
                            ->required()
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
                        TextInput::make('heading')->label(__('content.blocks.heading'))->maxLength(160),
                        Repeater::make('items')
                            ->label(__('content.blocks.items'))
                            ->schema([
                                Select::make('icon')->label(__('content.blocks.icon'))->options(SiteIcon::class),
                                TextInput::make('title')->label(__('content.blocks.card_title'))->required()->maxLength(120),
                                Textarea::make('text')->label(__('content.blocks.card_text'))->rows(3)->maxLength(600),
                            ])
                            ->columns(3)
                            ->minItems(1)
                            ->maxItems(12),
                    ]),
                Block::make('image')
                    ->label(__('content.blocks.image'))
                    ->icon(Heroicon::OutlinedPhoto)
                    ->schema([
                        self::image('image', $directory)
                            ->label(__('content.blocks.image'))
                            ->helperText(__('content.image_hint'))
                            ->required()
                            // الصورة المحفوظة في مقاطع السجل نفسه مسموحة، وما سواها يجب أن يُرفع الآن
                            ->preventFilePathTampering(allowFilePathUsing: fn (string $file, ?Model $record): bool => $record instanceof HasPublicImages
                                && in_array($file, ContentBlocks::images($record->getOriginal('blocks')), true)),
                        TextInput::make('caption')->label(__('content.blocks.caption'))->maxLength(200),
                    ]),
            ])
            ->collapsible()
            ->blockNumbers(false)
            ->columnSpanFull();
    }

    /**
     * رفع صورة على القرص العام: JPG أو PNG أو WebP، ولا SVG (docs/security.md).
     */
    public static function image(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(4096)
            ->rules(['dimensions:max_width=4000,max_height=4000'])
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
