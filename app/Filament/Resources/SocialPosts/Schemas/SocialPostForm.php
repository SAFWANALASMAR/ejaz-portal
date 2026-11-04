<?php

namespace App\Filament\Resources\SocialPosts\Schemas;

use App\Actions\Content\ImportSocialPosts;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use App\Filament\Forms\ContentFields;
use App\Models\SocialPost;
use App\Rules\PlainText;
use App\Rules\SafeUrl;
use App\Rules\SocialPostUrl;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * نموذج منشور التواصل: المنصة ونوعه وتاريخه، ورابطه الأصلي من نطاق منصته وحده (SocialPostUrl) بلا تكرار، ونصه، وصورة
 * اختيارية لا SVG.
 */
class SocialPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                Select::make('platform')->label(__('social_posts.fields.platform'))->options(SocialPlatform::class)->required()->native(false)->live(),
                Select::make('kind')->label(__('social_posts.fields.kind'))->options(SocialPostKind::class)->default(SocialPostKind::Post)->required()->native(false),
                DatePicker::make('posted_on')->label(__('social_posts.fields.posted_on'))->required()->native(false),
                TextInput::make('url')
                    ->label(__('social_posts.fields.url'))
                    ->helperText(__('social_posts.fields.url_hint'))
                    ->required()
                    ->maxLength(SocialPostUrl::MAX_LENGTH)
                    ->rule(fn (Get $get): SocialPostUrl => new SocialPostUrl(self::platform($get('platform'))))
                    // بلا تكرار بعد التسوية (الشرطة الأخيرة)، والمحذوف ناعماً لا يمنع إعادة إدخال منشوره (الفهرس الجزئي في الترحيل)
                    ->rule(fn (?SocialPost $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $taken = is_string($value) && SocialPost::query()->where('url', SocialPostUrl::normalize($value))
                            ->when($record?->exists, fn ($query) => $query->whereKeyNot($record?->getKey()))->exists();

                        if ($taken) {
                            $fail(__('social_posts.validation.duplicate'));
                        }
                    })
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : SocialPostUrl::normalize($state))
                    ->extraInputAttributes(['dir' => 'ltr']),
                Textarea::make('body')
                    ->label(__('social_posts.fields.body'))
                    ->helperText(__('social_posts.fields.body_hint'))
                    ->required()
                    ->rows(5)
                    ->maxLength(ImportSocialPosts::MAX_BODY)
                    ->rule(new PlainText(multiline: true))
                    ->columnSpanFull(),
                ContentFields::image('image', SocialPost::IMAGE_DIRECTORY)
                    ->label(__('social_posts.fields.image'))
                    ->helperText(__('social_posts.fields.image_hint'))
                    ->columnSpanFull(),
                // من الاستيراد وحده: مرجع للمحرر، لا يُجلب ولا يُعرض للزوار
                TextInput::make('source_image_url')
                    ->label(__('social_posts.fields.source_image_url'))
                    ->helperText(__('social_posts.fields.source_image_url_hint'))
                    ->maxLength(SocialPostUrl::MAX_LENGTH)
                    ->rule(new SafeUrl(allowInternal: false))
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->visible(fn (?SocialPost $record): bool => filled($record?->source_image_url))
                    ->columnSpanFull(),
                Toggle::make('is_published')->label(__('content.is_published')),
            ]),
        ]);
    }

    /**
     * حفظ النموذج برسالة التكرار تحت حقل الرابط إن سبقه إلى الرابط نفسه حفظ أو استيراد بعد فحص النموذج (الفهرس الفريد)، لا 500.
     *
     * @param  Closure(): Model  $save
     */
    public static function saveOnce(Closure $save): Model
    {
        try {
            return $save();
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'social_posts_url_unique')) {
                throw $exception;
            }

            throw ValidationException::withMessages(['data.url' => __('social_posts.validation.duplicate')]);
        }
    }

    private static function platform(mixed $state): ?SocialPlatform
    {
        return $state instanceof SocialPlatform ? $state : SocialPlatform::tryFrom(is_string($state) ? $state : '');
    }
}
