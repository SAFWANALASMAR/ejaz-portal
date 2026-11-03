<?php

namespace App\Filament\Resources\SitePages;

use App\Enums\PageRevisionStatus;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Forms\ContentFields;
use App\Filament\Forms\PageHeroUpload;
use App\Filament\Resources\SitePages\Pages\EditSitePage;
use App\Filament\Resources\SitePages\Pages\ListSitePages;
use App\Filament\Resources\SitePages\Pages\OpenSitePage;
use App\Filament\Resources\SitePages\RelationManagers\RevisionsRelationManager;
use App\Models\Page;
use App\Models\PageRevision;
use App\Support\EditablePages;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * «محرر الصفحات» (قرار صفوان، 31 أكتوبر 2026 — docs/decisions/2026-11-02-page-editor.md): صفحات الموقع الثابتة (EditablePages)
 * بعنوانها ووصفها وصورة رأسها وأقسامها (نصوص وصور، ومفتاح إظهار، وترتيب بالسحب)، تُحفظ مسودة ثم تُعاين ثم تُنشر، ولكل صفحة
 * نسخها بمن كتبها ومن نشرها ومتى، والرجوع إلى أي نسخة. لمن له pages.edit_all وحده (المسؤول التقني والمدير التنفيذي)،
 * ولا إنشاء ولا حذف: الصفحات من السجل.
 */
class SitePageResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'page_editor';

    protected static string $navigationGroupKey = 'content';

    protected static ?string $model = Page::class;

    protected static ?string $slug = 'site-pages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('slug', EditablePages::slugs());
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', PageRevision::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        // الصور المقبولة بلا رفع: صور هذه الصفحة ونسخها وحدها (EditablePages::knownImages)، تُقرأ مرة لكل نموذج لا لكل صورة
        $images = null;
        $known = function (string $file, ?Page $record) use (&$images): bool {
            if ($record === null) {
                return false;
            }

            $images ??= EditablePages::knownImages($record);

            return in_array($file, $images, true);
        };

        return $schema->components([
            Section::make(__('page_editor.sections.header'))->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('page_editor.fields.title'))->required()->maxLength(EditablePages::TITLE_MAX),
                // صورة الرأس بقواعد خانات «محتوى الرئيسية» نفسها (HomeContentPage)، وفي مجلدها
                PageHeroUpload::make('image')
                    ->label(__('page_editor.fields.image'))
                    ->helperText(__('page_editor.fields.image_hint'))
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(6144)
                    ->rules(['dimensions:max_width=5000,max_height=5000'])
                    ->preventFilePathTampering(allowFilePathUsing: $known)
                    ->disk('public')
                    ->directory(PageRevision::heroImageDirectory())
                    ->visibility('public'),
                Textarea::make('summary')->label(__('page_editor.fields.summary'))->helperText(__('page_editor.fields.summary_hint'))->rows(2)->maxLength(EditablePages::SUMMARY_MAX)->columnSpanFull(),
            ]),
            Section::make(__('page_editor.sections.blocks'))->description(__('page_editor.sections.blocks_hint'))->columnSpanFull()->schema([
                ContentFields::blocks(Page::IMAGE_DIRECTORY, $known)->label(__('page_editor.fields.blocks'))->maxItems(EditablePages::BLOCKS_MAX),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withExists(['revisions as has_draft' => fn (Builder $revisions) => $revisions->where('status', PageRevisionStatus::Draft->value)]))
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('slug')->label(__('page_editor.fields.page'))
                    ->formatStateUsing(fn (string $state): string => __('page_editor.pages.'.EditablePages::keyForSlug($state))),
                TextColumn::make('title')->label(__('page_editor.fields.title')),
                IconColumn::make('is_published')->label(__('page_editor.fields.is_published'))->boolean(),
                IconColumn::make('has_draft')->label(__('page_editor.fields.has_draft'))->boolean(),
                TextColumn::make('updated_at')->label(__('page_editor.fields.updated_at'))->since(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSitePages::route('/'),
            'open' => OpenSitePage::route('/open/{key}'),
            'edit' => EditSitePage::route('/{record}/edit'),
        ];
    }
}
