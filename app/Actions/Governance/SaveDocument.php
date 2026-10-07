<?php

namespace App\Actions\Governance;

use App\Models\Document;
use App\Models\User;
use App\Rules\RealPdf;
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
            $this->checkFile((string) $document->file);
            $document->file_size = Storage::disk(Document::DISK)->size((string) $document->file);
        }

        DB::transaction(fn () => $document->save());

        if ($previous !== null && $previous !== $document->file && self::inDirectory($previous)) {
            Storage::disk(Document::DISK)->delete($previous);
        }

        return $document;
    }

    /**
     * يُفحص الملف المحفوظ نفسه، ولو فُحص عند الرفع، فقد يُستدعى الإجراء من غير اللوحة (الاستيراد).
     *
     * @throws ValidationException
     */
    private function checkFile(string $path): void
    {
        $disk = Storage::disk(Document::DISK);
        $problem = self::inDirectory($path) && $disk->exists($path) ? RealPdf::problem((string) $disk->get($path)) : 'validation.real_pdf';

        if ($problem === null) {
            return;
        }

        if (self::inDirectory($path)) {
            $disk->delete($path);
        }

        throw ValidationException::withMessages(['file' => __($problem)]);
    }

    private static function inDirectory(string $path): bool
    {
        return str_starts_with($path, Document::DIRECTORY.'/') && ! str_contains($path, '..') && ! str_contains($path, "\0");
    }
}
