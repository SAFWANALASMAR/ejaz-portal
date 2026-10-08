<?php

namespace App\Actions\Assembly;

use App\Enums\AssemblyFileAudience;
use App\Enums\AssemblyFileCategory;
use App\Models\AssemblyFile;
use App\Models\User;
use App\Support\PrivatePdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ ملف في مكتبة ملفات الأعضاء (السكرتير): PDF حقيقي في مجلده، ثم الحفظ، ثم حذف الملف السابق إن
 * استُبدل. ويُسجَّل الحفظ (LogsActivity).
 */
class SaveAssemblyFile
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, AssemblyFile $file, array $data): AssemblyFile
    {
        $allowed = $file->exists ? $actor->can('update', $file) : $actor->can('create', AssemblyFile::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(AssemblyFileCategory::class)],
            'audience' => ['required', Rule::enum(AssemblyFileAudience::class)],
            'file' => ['required', 'string', 'max:255'],
        ])->validate();

        $previous = $file->exists ? $file->file : null;
        $file->fill($validated);

        if ($file->file !== $previous) {
            // ملف سجل آخر (ولو محذوف ناعماً) لا يُعاد استعماله، فلا يحذفه استبدالُه فيه
            if (AssemblyFile::withTrashed()->where('file', $file->file)->when($file->exists, fn ($query) => $query->whereKeyNot($file->getKey()))->exists()) {
                throw ValidationException::withMessages(['file' => __('validation.file_in_use')]);
            }

            $file->file_size = PrivatePdf::check(AssemblyFile::disk(), AssemblyFile::DIRECTORY, (string) $file->file, 'file');
        }

        if (! $file->exists) {
            $file->uploaded_by = $actor->id;
        }

        DB::transaction(fn () => $file->save());

        if ($previous !== null && $previous !== $file->file && PrivatePdf::inDirectory($previous, AssemblyFile::DIRECTORY)) {
            Storage::disk(AssemblyFile::disk())->delete($previous);
        }

        return $file;
    }
}
