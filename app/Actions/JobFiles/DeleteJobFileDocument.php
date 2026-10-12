<?php

namespace App\Actions\JobFiles;

use App\Models\JobFileDocument;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * حذف مستند من ملف وظيفة (JobFileDocumentPolicy::delete). الحذف ناعم ويبقى الملف في القرص، ويُسجَّل.
 */
class DeleteJobFileDocument
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, JobFileDocument $document): void
    {
        if (! $actor->can('delete', $document)) {
            throw new AuthorizationException;
        }

        $document->delete();
    }
}
