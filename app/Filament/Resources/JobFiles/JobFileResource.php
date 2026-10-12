<?php

namespace App\Filament\Resources\JobFiles;

use App\Enums\JobFileIcon;
use App\Enums\RoleName;
use App\Filament\Resources\JobFiles\Pages\CreateJobFile;
use App\Filament\Resources\JobFiles\Pages\EditJobFile;
use App\Filament\Resources\JobFiles\Pages\ListJobFiles;
use App\Filament\Resources\JobFiles\Pages\ViewJobFile;
use App\Filament\Resources\JobFiles\RelationManagers\DocumentsRelationManager;
use App\Models\JobFile;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * ملفات الوظائف (requirements/11): بطاقات بأيقونة كل ملف ومسماه، مجمّعة بالوظيفة المسؤولة. يرى الموظف ما
 * هو مسؤول عنه أو مشارك فيه، ومن يملك job_files.view كل الملفات (JobFile::visibleTo). وصفحة الملف فيها
 * مستنداته، ويُسجَّل فتحها. وإنشاء الملفات وتعديل بياناتها للمدير التنفيذي (SaveJobFile).
 */
class JobFileResource extends Resource
{
    protected static ?string $model = JobFile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('job_files.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('job_files.plural');
    }

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()->whereIn('id', JobFile::visibleTo($user)->select('id'));
    }

    /**
     * @return array<string, string>
     */
    public static function staffOptions(?string $except = null): array
    {
        return collect(RoleName::staff())
            ->reject(fn (RoleName $role): bool => $role->value === $except)
            ->mapWithKeys(fn (RoleName $role): array => [$role->value => $role->getLabel()])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('job_files.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                Textarea::make('description')->label(__('job_files.fields.description'))->rows(3)->maxLength(2000)->columnSpanFull(),
                Select::make('responsible_role')->label(__('job_files.fields.responsible_role'))->options(self::staffOptions())->required(),
                Select::make('icon')->label(__('job_files.fields.icon'))->options(JobFileIcon::class)->default(JobFileIcon::Folder->value)->required(),
                CheckboxList::make('participant_roles')->label(__('job_files.fields.participant_roles'))->options(self::staffOptions())->columns(3)->default([])->columnSpanFull(),
                Toggle::make('participants_contribute')->label(__('job_files.fields.participants_contribute'))->helperText(__('job_files.fields.participants_contribute_hint')),
                TextInput::make('sort')->label(__('job_files.fields.sort'))->integer()->minValue(0)->maxValue(10000)->default(0),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextEntry::make('description')->label(__('job_files.fields.description'))->placeholder('—')->columnSpanFull(),
                TextEntry::make('responsible_role')->label(__('job_files.fields.responsible_role'))->badge(),
                TextEntry::make('participant_roles')
                    ->label(__('job_files.fields.participant_roles'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RoleName::tryFrom($state)?->getLabel() ?? $state)
                    ->placeholder(__('job_files.fields.no_participants')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $order = RoleName::values(RoleName::staff());

        return $table
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginated(false)
            ->defaultSort('sort')
            ->defaultGroup(
                Group::make('responsible_role')
                    ->label(__('job_files.fields.responsible_role'))
                    ->getTitleFromRecordUsing(fn (JobFile $record): string => $record->responsible_role->getLabel())
                    // بترتيب الوظائف في الدليل، والقيم من التعداد لا من مدخلات
                    ->orderQueryUsing(fn (Builder $query): Builder => $query->orderByRaw(
                        'case responsible_role '.implode(' ', array_fill(0, count($order), 'when ? then ?')).' end',
                        collect($order)->flatMap(fn (string $role, int $index): array => [$role, $index])->all(),
                    ))
                    ->collapsible(),
            )
            ->columns([
                Split::make([
                    IconColumn::make('icon')->size(IconSize::TwoExtraLarge)->color('primary')->grow(false),
                    Stack::make([
                        TextColumn::make('title')->weight(FontWeight::Bold)->searchable(),
                        TextColumn::make('description')->color('gray')->limit(110),
                        TextColumn::make('from_matrix')
                            ->badge()
                            ->color('info')
                            ->state(fn (JobFile $record): ?string => $record->from_matrix ? __('job_files.fields.from_matrix') : null),
                    ])->space(1),
                ]),
            ])
            ->recordUrl(fn (JobFile $record): string => self::getUrl('view', ['record' => $record]))
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobFiles::route('/'),
            'create' => CreateJobFile::route('/create'),
            'view' => ViewJobFile::route('/{record}'),
            'edit' => EditJobFile::route('/{record}/edit'),
        ];
    }
}
