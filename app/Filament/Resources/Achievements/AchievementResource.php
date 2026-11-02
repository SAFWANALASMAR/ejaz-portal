<?php

namespace App\Filament\Resources\Achievements;

use App\Actions\Achievements\SaveAchievement;
use App\Enums\AchievementStatus;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\Achievements\Pages\CreateAchievement;
use App\Filament\Resources\Achievements\Pages\EditAchievement;
use App\Filament\Resources\Achievements\Pages\ListAchievements;
use App\Models\Achievement;
use App\Rules\PlainText;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * «إنجازاتنا» في اللوحة: تضيفها وتعدّلها وتنشرها وتحذفها الإدارة التنفيذية وحدها (achievements.* — AchievementPolicy). والحفظ
 * عبر SaveAchievement (فحص المرفق وإعادة ترميز الصورة)، والحذف ناعم ويُستعاد، وكل عملية في سجل العمليات (LogsActivity).
 * والمرفق يُفتح برابطه المؤقت الموقّع، لا رابط تخزين.
 */
class AchievementResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'achievements';

    protected static string $navigationGroupKey = 'assembly';

    protected static ?string $model = Achievement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('achievements.fields.title'))->required()->maxLength(200)->rule(new PlainText)->columnSpanFull(),
                DatePicker::make('achieved_on')->label(__('achievements.fields.achieved_on'))->required()
                    ->minDate(SaveAchievement::MIN_DATE)->maxDate(fn (): string => SaveAchievement::maxDate())
                    ->default(fn (): string => SaveAchievement::maxDate()),
                Select::make('status')->label(__('achievements.fields.status'))->options(AchievementStatus::class)
                    ->default(AchievementStatus::Draft->value)->required()->native(false),
                Textarea::make('details')->label(__('achievements.fields.details'))->required()->maxLength(5000)->rule(new PlainText(multiline: true))->rows(6)->columnSpanFull(),
                FileUpload::make('attachment')
                    ->label(__('achievements.fields.attachment'))
                    ->helperText(__('achievements.fields.attachment_hint'))
                    ->disk(Achievement::disk())
                    ->directory(Achievement::DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(20480)
                    ->storeFileNamesIn('attachment_name')
                    // لا رابط تخزين في اللوحة: المرفق يُفتح من إجراء «عرض المرفق» برابطه الموقّع
                    ->previewable(false)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('achieved_on', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('achievements.fields.title'))->searchable()->wrap(),
                TextColumn::make('achieved_on')->label(__('achievements.fields.achieved_on'))->date('Y-m-d')->sortable(),
                TextColumn::make('status')->label(__('achievements.fields.status'))->badge(),
                TextColumn::make('attachment_name')->label(__('achievements.fields.attachment'))->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('achievements.fields.status'))->options(AchievementStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                self::attachmentAction(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /**
     * فتح المرفق برابطه المؤقت الموقّع لمن يطّلع على الإنجاز، والرابط يُفحص عند الطلب ويُسجَّل اطلاع المسودة.
     */
    public static function attachmentAction(): Action
    {
        return Action::make('attachment')
            ->label(__('achievements.actions.attachment'))
            ->icon(Heroicon::OutlinedPaperClip)
            ->visible(fn (Achievement $record): bool => $record->attachment !== null && ! $record->trashed())
            ->authorize(fn (Achievement $record): bool => auth()->user()?->can('view', $record) ?? false)
            ->url(fn (Achievement $record): ?string => $record->attachmentUrl())
            ->openUrlInNewTab();
    }

    /**
     * المحذوفة حذفاً ناعماً تظهر بمرشّحها، لتُستعاد.
     *
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAchievements::route('/'),
            'create' => CreateAchievement::route('/create'),
            'edit' => EditAchievement::route('/{record}/edit'),
        ];
    }
}
