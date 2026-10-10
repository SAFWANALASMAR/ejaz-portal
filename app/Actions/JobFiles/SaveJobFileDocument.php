<?php

namespace App\Actions\JobFiles;

use App\Models\JobFile;
use App\Models\JobFileDocument;
use App\Models\User;
use App\Support\ImageSanitizer;
use App\Support\PrivatePdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * إضافة مستند إلى ملف وظيفة أو تعديله (JobFileDocumentPolicy). الملف PDF حقيقي (PrivatePdf) أو صورة يُعاد
 * ترميزها (ImageSanitizer)، في مجلده في القرص الخاص. والمستبدَل يُحذف من القرص بعد الحفظ، ومسار مستند
 * آخر لا يُعاد استعماله.
 */
class SaveJobFileDocument
{
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

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
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:250'],
            'description' => ['nullable', 'string', 'max:2000'],
            'path' => ['required', 'string', 'max:255'],
            'original_name' => ['nullable', 'string', 'max:200'],
        ])->validate();

        $previous = $document->exists ? $document->path : null;
        $path = (string) $validated['path'];

        if ($path !== $previous) {
            $document->size = self::check($path);
            $document->path = $path;
            $document->original_name = str_replace(['/', '\\'], '-', (string) ($validated['original_name'] ?? basename($path)));
        }

        $document->fill(['title' => $validated['title'], 'description' => $validated['description'] ?? null]);
        $document->updated_by = $actor->id;

        if (! $document->exists) {
            $document->job_file_id = $file->id;
            $document->created_by = $actor->id;
        }

        $document->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk(JobFileDocument::disk())->delete($previous);
        }

        return $document;
    }

    /**
     * @return int حجم الملف بالبايت
     *
     * @throws ValidationException
     */
    private static function check(string $path): int
    {
        if (JobFileDocument::withTrashed()->where('path', $path)->exists()) {
            throw ValidationException::withMessages(['path' => __('validation.file_in_use')]);
        }

        $disk = Storage::disk(JobFileDocument::disk());
        $inDirectory = PrivatePdf::inDirectory($path, JobFileDocument::DIRECTORY);
        $type = $inDirectory && $disk->exists($path) ? (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer((string) $disk->get($path)) : '';

        if ($type === 'application/pdf') {
            return PrivatePdf::check(JobFileDocument::disk(), JobFileDocument::DIRECTORY, $path, 'path');
        }

        if (in_array($type, self::IMAGE_TYPES, true)) {
            try {
                ImageSanitizer::strip(JobFileDocument::disk(), $path);

                return (int) $disk->size($path);
            } catch (RuntimeException) {
                // يُحذف أدناه
            }
        }

        if ($inDirectory) {
            $disk->delete($path);
        }

        throw ValidationException::withMessages(['path' => __('job_files.validation.invalid_file')]);
    }
}
