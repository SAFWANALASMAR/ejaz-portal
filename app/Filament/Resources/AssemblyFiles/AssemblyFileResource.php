<?php

namespace App\Filament\Resources\AssemblyFiles;

use App\Actions\Activity\LogAccess;
use App\Enums\ActivityEvent;
use App\Enums\AssemblyFileAudience;
use App\Enums\AssemblyFileCategory;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\AssemblyFiles\Pages\CreateAssemblyFile;
use App\Filament\Resources\AssemblyFiles\Pages\EditAssemblyFile;
use App\Filament\Resources\AssemblyFiles\Pages\ListAssemblyFiles;
use App\Models\AssemblyFile;
use App\Models\User;
use App\Rules\RealPdf;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * مكتبة ملفات الأعضاء. يديرها السكرتير ويطّلع عليها المجلس (assembly_files.* — AssemblyFilePolicy). والحذف
 * ناعم ويُستعاد، والتنزيل من اللوحة بإجراء يفحص الصلاحية ويُسجَّل.
 */
class AssemblyFileResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'assembly.file';

    protected static string $navigationGroupKey = 'assembly';

    protected static ?string $model = AssemblyFile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('assembly.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                Select::make('category')->label(__('assembly.fields.category'))->options(AssemblyFileCategory::class)->required(),
                Select::make('audience')->label(__('assembly.fields.audience'))->options(AssemblyFileAudience::class)->default(AssemblyFileAudience::Members->value)->required(),
                FileUpload::make('file')
                    ->label(__('assembly.fields.file'))
                    ->required()
                    ->disk(AssemblyFile::disk())
                    ->directory(AssemblyFile::DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(20480)
                    ->rules([new RealPdf])
                    // لا رابط مؤقت موقّع: الملف يُنزَّل من إجراء «تنزيل الملف» بعد فحص الصلاحية
                    ->previewable(false)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('assembly.fields.title'))->searchable()->wrap(),
                TextColumn::make('category')->label(__('assembly.fields.category'))->badge(),
                TextColumn::make('audience')->label(__('assembly.fields.audience'))->badge(),
                TextColumn::make('created_at')->label(__('assembly.fields.created_at'))->date('Y-m-d')->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')->label(__('assembly.fields.category'))->options(AssemblyFileCategory::class),
                SelectFilter::make('audience')->label(__('assembly.fields.audience'))->options(AssemblyFileAudience::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                self::downloadAction(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /**
     * تنزيل الملف لمن يملك الاطلاع عليه، ويُسجَّل.
     */
    public static function downloadAction(): Action
    {
        return Action::make('downloadFile')
            ->label(__('assembly.actions.download'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->authorize(fn (AssemblyFile $record): bool => auth()->user()?->can('view', $record) ?? false)
            ->action(function (AssemblyFile $record, LogAccess $log): StreamedResponse {
                /** @var User $actor */
                $actor = auth()->user();
                $log->handle($actor, $record, 'assembly', ActivityEvent::FileDownloaded, ['file' => 'file']);

                return Storage::disk(AssemblyFile::disk())->download($record->file, $record->downloadName(), ['Content-Type' => 'application/pdf']);
            });
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
            'index' => ListAssemblyFiles::route('/'),
            'create' => CreateAssemblyFile::route('/create'),
            'edit' => EditAssemblyFile::route('/{record}/edit'),
        ];
    }
}
