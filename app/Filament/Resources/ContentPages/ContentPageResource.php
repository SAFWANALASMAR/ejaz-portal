<?php

namespace App\Filament\Resources\ContentPages;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\ContentPages\Pages\CreateContentPage;
use App\Filament\Resources\ContentPages\Pages\EditContentPage;
use App\Filament\Resources\ContentPages\Pages\ListContentPages;
use App\Filament\Resources\ContentPages\Schemas\ContentPageForm;
use App\Filament\Resources\ContentPages\Tables\ContentPagesTable;
use App\Models\Page;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * صفحات المحتوى (النموذج Page). الاسم ContentPages لا Pages، حتى لا يلتبس بمجلد Pages الذي
 * يضع فيه Filament صفحات كل مورد. الصلاحيات: pages.* — PagePolicy.
 */
class ContentPageResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'pages';

    protected static string $navigationGroupKey = 'content';

    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ContentPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentPagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentPages::route('/'),
            'create' => CreateContentPage::route('/create'),
            'edit' => EditContentPage::route('/{record}/edit'),
        ];
    }
}
