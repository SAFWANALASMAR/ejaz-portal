<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Actions\Governance\SaveDocument;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * صفحتا إنشاء الوثيقة وتعديلها تحفظان عبر SaveDocument، وتعرضان أخطاءه تحت حقولها.
 * والملف المرفوض حُذف، فيعود حقله إلى الملف المحفوظ ليُحفظ النموذج بعدها.
 */
trait SavesDocumentThroughAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveDocument(Document $document, array $data): Document
    {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            return app(SaveDocument::class)->handle($actor, $document, $data);
        } catch (ValidationException $exception) {
            if (array_key_exists('file', $exception->errors())) {
                $stored = $document->getOriginal('file');
                $this->data['file'] = is_string($stored) && $stored !== '' ? [(string) Str::uuid() => $stored] : [];
            }

            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key) => ['data.'.$key => $messages])->all(),
            );
        }
    }
}
