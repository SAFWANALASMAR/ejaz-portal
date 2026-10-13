<?php

namespace App\Actions\JobFiles;

use App\Models\JobFile;
use App\Models\JobFileDocument;
use App\Models\User;
use App\Support\PrivateUpload;
use App\Support\StoragePath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * إضافة مستند إلى ملف وظيفة أو تعديله (JobFileDocumentPolicy). الملف PDF حقيقي (PrivatePdf) أو صورة يُعاد
 * ترميزها (ImageSanitizer)، في مجلده في القرص الخاص، بسقف للحجم وللأبعاد. والمستبدَل يبقى في القرص كما يبقى
 * ملف المحذوف ناعماً، ومساره القديم والجديد في سجل التعديل. ومسار مستند آخر لا يُعاد استعماله (وقيد فريد
 * في القاعدة)، والمرفوع المرفوض يُحذف. وامتداد اسم التنزيل من نوع الملف المكتشف لا من اسمه المرسل.
 */
class SaveJobFileDocument
{
    /**
     * @param  array<string, mixed>  $data  title، وdescription، وpath، وoriginal_name
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, JobFile $file, JobFileDocument $document, array $data): JobFileDocument
    {
        $allowed = $document->exists
            ? $document->job_file_id === $file->id && $actor->can('update', $document)
            : $actor->can('create', [JobFileDocument::class, $file]);

        if (! $allowed) {
            self::discard($data['path'] ?? null);

            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:250'],
            'description' => ['nullable', 'string', 'max:2000'],
            'path' => ['required', 'string', 'max:255'],
            'original_name' => ['nullable', 'string', 'max:200'],
        ])->validate();

        $path = (string) $validated['path'];
        $replaced = $path !== ($document->exists ? $document->path : null);

        if ($replaced) {
            [$document->size, $extension] = self::check($path);
            $document->path = $path;
            $name = pathinfo(strtr((string) ($validated['original_name'] ?? ''), ['/' => '-', '\\' => '-']), PATHINFO_FILENAME);
            $document->original_name = mb_substr($name !== '' ? $name : 'document', 0, 190).'.'.$extension;
        }

        $document->fill(['title' => $validated['title'], 'description' => $validated['description'] ?? null]);
        $document->updated_by = $actor->id;

        if (! $document->exists) {
            $document->job_file_id = $file->id;
            $document->created_by = $actor->id;
        }

        try {
            $document->save();
        } catch (UniqueConstraintViolationException) {
            // مستند آخر حُفظ بالمسار نفسه في اللحظة نفسها: يبقى الملف له
            throw ValidationException::withMessages(['path' => __('validation.file_in_use')]);
        }

        return $document;
    }

    /**
     * @return array{int, string} حجم الملف بالبايت، وامتداده من نوعه
     *
     * @throws ValidationException
     */
    private static function check(string $path): array
    {
        if (JobFileDocument::withTrashed()->where('path', $path)->exists()) {
            throw ValidationException::withMessages(['path' => __('validation.file_in_use')]);
        }

        $file = PrivateUpload::check(JobFileDocument::disk(), JobFileDocument::DIRECTORY, $path, 'path', __('job_files.validation.invalid_file'));

        return [$file['size'], $file['extension']];
    }

    /**
     * مرفوع رُفض قبل حفظه: يُحذف إن كان في مجلد الملفات ولا يستعمله مستند.
     */
    private static function discard(mixed $path): void
    {
        if (is_string($path) && StoragePath::inDirectory($path, JobFileDocument::DIRECTORY) && ! JobFileDocument::withTrashed()->where('path', $path)->exists()) {
            Storage::disk(JobFileDocument::disk())->delete($path);
        }
    }
}
