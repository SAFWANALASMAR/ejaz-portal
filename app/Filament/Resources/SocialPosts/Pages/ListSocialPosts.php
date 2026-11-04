<?php

namespace App\Filament\Resources\SocialPosts\Pages;

use App\Actions\Content\ImportSocialPosts;
use App\Filament\Resources\SocialPosts\SocialPostResource;
use App\Models\SocialPost;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListSocialPosts extends ListRecords
{
    protected static string $resource = SocialPostResource::class;

    /**
     * الصفوف المرفوضة المذكورة في الإشعار، والباقي عددٌ.
     */
    private const SHOWN_REJECTIONS = 15;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('import')
                ->label(__('social_posts.import.action'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->authorize('create', SocialPost::class)
                ->modalDescription(__('social_posts.import.hint', ['platforms' => ImportSocialPosts::platforms()]))
                ->schema([
                    FileUpload::make('file')->label(__('social_posts.import.file'))->required()->storeFiles(false)
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->maxSize(2048),
                ])
                ->action(fn (array $data) => $this->import($data['file'])),
            Action::make('template')
                ->label(__('social_posts.import.template'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->authorize('create', SocialPost::class)
                ->action(fn (): StreamedResponse => ImportSocialPosts::template()),
        ];
    }

    private function import(TemporaryUploadedFile $file): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        // نسخة محلية مؤقتة: الرفع المؤقت قد يكون على S3 في الإنتاج فلا مسار محلياً له
        $path = (string) tempnam(sys_get_temp_dir(), 'social');
        file_put_contents($path, (string) $file->get());

        try {
            $result = app(ImportSocialPosts::class)->handle($actor, $path, $file->getClientOriginalExtension());
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

            return;
        } finally {
            // والأصل المرفوع وملف بياناته (اسمه الأصلي) لا يبقيان في مجلد الرفع المؤقت حتى تنظيفه بعد يوم
            @unlink($path);
            $file->delete();
            FileUploadConfiguration::storage()->delete(FileUploadConfiguration::path($file->getFilename().'.json'));
        }

        Notification::make()->success()->title(__('social_posts.import.done', ['count' => $result['created']]))->send();

        if ($result['rejected'] === []) {
            return;
        }

        $lines = array_map(fn (array $row): string => __('social_posts.import.rejected_row', $row), array_slice($result['rejected'], 0, self::SHOWN_REJECTIONS));
        $more = count($result['rejected']) - self::SHOWN_REJECTIONS;

        if ($more > 0) {
            $lines[] = __('social_posts.import.rejected_more', ['count' => $more]);
        }

        Notification::make()->warning()->persistent()
            ->title(__('social_posts.import.rejected_title', ['count' => count($result['rejected'])]))
            ->body(implode('<br>', array_map(e(...), $lines)))
            ->send();
    }
}
