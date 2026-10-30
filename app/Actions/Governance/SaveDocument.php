<?php

namespace App\Actions\Governance;

use App\Models\Document;
use App\Models\User;
use App\Rules\RealPdf;
use App\Rules\SafeUrl;
use App\Support\StoragePath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * حفظ وثيقة حوكمة: الصلاحية، ثم ملف أو رابط https أحدهما وحده، ثم فحص الملف الجديد في القرص الخاص (PDF حقيقي من مجلد
 * الوثائق)، ثم الحفظ، ثم حذف الملف السابق إن استُبدل. والوثيقة تُسجَّل وتمسح ذاكرة المحتوى
 * بنفسها (LogsActivity وFlushesContentCache).
 */
class SaveDocument
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException ملف ليس PDF حقيقياً أو خارج مجلد الوثائق، أو رابط غير آمن، أو الاثنان معاً أو لا شيء
     */
    public function handle(User $actor, Document $document, array $data): Document
    {
        $allowed = $document->exists ? $actor->can('update', $document) : $actor->can('create', Document::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $previous = $document->exists ? $document->file : null;
        $document->fill($data);
        // الحقل الفارغ غياب، فلا يُحسب ملفاً ولا رابطاً
        $document->file = filled($document->file) ? $document->file : null;
        $document->url = filled($document->url) ? $document->url : null;

        $newFile = $document->file !== $previous ? $document->file : null;

        if ($newFile !== null) {
            $this->checkFile($document);
        }

        try {
            $this->checkSource($document);
        } catch (ValidationException $exception) {
            // الملف الجديد اجتاز فحصه فهو في مجلد الوثائق ولا تملكه وثيقة أخرى، ورفضُ الحفظ لا يُبقيه يتيماً
            if ($newFile !== null) {
                Storage::disk(Document::disk())->delete($newFile);
            }

            throw $exception;
        }

        if ($document->file !== $previous) {
            $document->file_size = $newFile === null ? null : Storage::disk(Document::disk())->size($newFile);
        }

        DB::transaction(fn () => $document->save());

        if ($previous !== null && $previous !== $document->file && StoragePath::inDirectory($previous, Document::DIRECTORY)) {
            Storage::disk(Document::disk())->delete($previous);
        }

        return $document;
    }

    /**
     * ملف أو رابط مباشر، أحدهما وحده (docs/decisions/2026-10-30-document-url.md). والرابط https
     * كامل بلا بيانات دخول ولا محارف مخفية (SafeUrl)، فلا javascript: ولا data: ولا http.
     *
     * @throws ValidationException
     */
    private function checkSource(Document $document): void
    {
        Validator::make(
            ['file' => $document->file, 'url' => $document->url],
            [
                'file' => ['required_without:url', 'prohibits:url'],
                'url' => ['nullable', 'string', 'max:2048', new SafeUrl(allowInternal: false)],
            ],
            [
                'file.required_without' => __('governance.errors.file_or_url'),
                'file.prohibits' => __('governance.errors.file_and_url'),
            ],
            ['url' => __('governance.fields.url')],
        )->validate();
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
