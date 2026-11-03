<?php

namespace App\Filament\Resources\SocialPosts;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\SocialPosts\Pages\CreateSocialPost;
use App\Filament\Resources\SocialPosts\Pages\EditSocialPost;
use App\Filament\Resources\SocialPosts\Pages\ListSocialPosts;
use App\Filament\Resources\SocialPosts\Schemas\SocialPostForm;
use App\Filament\Resources\SocialPosts\Tables\SocialPostsTable;
use App\Models\SocialPost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * «منشورات التواصل» لمحرر المحتوى ومديري النظام (طلب صفوان، 3 نوفمبر 2026). الصلاحيات: social_posts.* — SocialPostPolicy.
 */
class SocialPostResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'social_posts';

    protected static string $navigationGroupKey = 'media';

    protected static ?string $model = SocialPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return SocialPostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocialPostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialPosts::route('/'),
            'create' => CreateSocialPost::route('/create'),
            'edit' => EditSocialPost::route('/{record}/edit'),
        ];
    }
}
