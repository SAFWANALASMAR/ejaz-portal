<?php

namespace App\Filament\Resources\JobFiles\RelationManagers;

use App\Actions\JobFiles\DeleteJobFileDocument;
use App\Actions\JobFiles\SaveJobFileDocument;
use App\Models\JobFile;
use App\Models\JobFileDocument;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * مستندات ملف الوظيفة في صفحته: الإضافة والتعديل والحذف بـ JobFileDocumentPolicy عبر إجراءات الوحدة،
 * والتنزيل برابط مؤقت موقّع (JobFileDocumentController).
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    private const LINK_MINUTES = 5;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('job_files.documents.plural');
    }

    /**
     * صفحة الملف صفحة عرض، والإضافة فيها لمن تسمح له السياسة.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    protected function getCreateAuthorizationResponse(): Response
    {
        return Gate::inspect('create', [JobFileDocument::class, $this->jobFile()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label(__('job_files.fields.document_title'))->required()->maxLength(250),
            Textarea::make('description')->label(__('job_files.fields.document_description'))->rows(3)->maxLength(2000),
            FileUpload::make('path')
                ->label(__('job_files.fields.file'))
                ->required()
                ->disk(JobFileDocument::disk())
                ->directory(JobFileDocument::DIRECTORY)
                ->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(20480)
                ->storeFileNamesIn('original_name')
                ->previewable(false),
        ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('job_files.documents.model'))
            ->pluralModelLabel(__('job_files.documents.plural'))
            ->emptyStateHeading(__('job_files.documents.empty'))
            ->modifyQueryUsing(fn (Builder $query) => $query->with('creator'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('job_files.fields.document_title'))->searchable()->wrap(),
                TextColumn::make('original_name')->label(__('job_files.fields.original_name'))->toggleable(),
                TextColumn::make('creator.name')->label(__('job_files.fields.created_by'))->placeholder('—'),
                TextColumn::make('updated_at')->label(__('job_files.fields.updated_at'))->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data, CreateAction $action): Model => $this->save(new JobFileDocument, $data, fn () => $action->halt())),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('job_files.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->authorize(fn (JobFileDocument $record): bool => self::actor()->can('view', $record))
                    ->url(fn (JobFileDocument $record): string => URL::temporarySignedRoute('job-files.documents.download', now()->addMinutes(self::LINK_MINUTES), ['document' => $record]))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->using(fn (JobFileDocument $record, array $data, EditAction $action): Model => $this->save($record, $data, fn () => $action->halt())),
                DeleteAction::make()
                    ->using(function (JobFileDocument $record, DeleteJobFileDocument $delete): bool {
                        $delete->handle(self::actor(), $record);

                        return true;
                    }),
            ]);
    }

    /**
     * الحفظ عبر الإجراء، وخطؤه يظهر إشعاراً ويبقى النموذج مفتوحاً.
     *
     * @param  array<string, mixed>  $data
     */
    private function save(JobFileDocument $document, array $data, Closure $halt): JobFileDocument
    {
        try {
            return app(SaveJobFileDocument::class)->handle(self::actor(), $this->jobFile(), $document, $data);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
            $halt();

            return $document;
        }
    }

    private function jobFile(): JobFile
    {
        /** @var JobFile */
        return $this->getOwnerRecord();
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
