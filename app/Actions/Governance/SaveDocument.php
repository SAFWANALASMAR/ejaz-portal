<?php

namespace App\Actions\Governance;

use App\Models\Document;
use App\Models\User;
use App\Rules\RealPdf;
use App\Support\StoragePath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * حفظ وثيقة حوكمة: الصلاحية، ثم فحص الملف الجديد في القرص الخاص (PDF حقيقي من مجلد
 * الوثائق)، ثم الحفظ، ثم حذف الملف السابق إن استُبدل. والوثيقة تُسجَّل وتمسح ذاكرة المحتوى
 * بنفسها (LogsActivity وFlushesContentCache).
 */
class SaveDocument
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException ملف ليس PDF حقيقياً أو خارج مجلد الوثائق
     */
    public function handle(User $actor, Document $document, array $data): Document
    {
        $allowed = $document->exists ? $actor->can('update', $document) : $actor->can('create', Document::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $previous = $document->exists ? $document->file : null;
        $document->fill($data);

        if ($document->file !== $previous) {
            $this->checkFile($document);
            $document->file_size = Storage::disk(Document::disk())->size((string) $document->file);
        }

        DB::transaction(fn () => $document->save());

        if ($previous !== null && $previous !== $document->file && StoragePath::inDirectory($previous, Document::DIRECTORY)) {
            Storage::disk(Document::disk())->delete($previous);
        }

        return $document;
    }

    /**
     * يُفحص الملف المحفوظ نفسه، ولو فُحص عند الرفع، فقد يُستدعى الإجراء من غير اللوحة (الاستيراد).
     *
     * @throws ValidationException
     */
    private function checkFile(Document $document): void
    {
        $path = (string) $document->file;

        // ملف وثيقة أخرى (ولو محذوفة ناعماً) لا يُعاد استعماله، فلا يحذفه استبدالُه فيها
        $inUse = Document::withTrashed()->where('file', $path)->when($document->exists, fn ($query) => $query->whereKeyNot($document->getKey()))->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['file' => __('validation.file_in_use')]);
        }

        $disk = Storage::disk(Document::disk());
        $problem = StoragePath::inDirectory($path, Document::DIRECTORY) && $disk->exists($path) ? RealPdf::problem((string) $disk->get($path)) : 'validation.real_pdf';

        if ($problem === null) {
            return;
        }

        if (StoragePath::inDirectory($path, Document::DIRECTORY)) {
            $disk->delete($path);
        }

        throw ValidationException::withMessages(['file' => __($problem)]);
    }
}
