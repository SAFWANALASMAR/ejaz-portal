<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Actions\Governance\SaveDocument;
use App\Filament\Concerns\SavesThroughAction;
use App\Models\Document;
use App\Models\User;

/**
 * صفحتا إنشاء الوثيقة وتعديلها تحفظان عبر SaveDocument (SavesThroughAction).
 */
trait SavesDocumentThroughAction
{
    use SavesThroughAction;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveDocument(Document $document, array $data): Document
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($document, ['file'], fn (): Document => app(SaveDocument::class)->handle($actor, $document, $data));
    }
}
