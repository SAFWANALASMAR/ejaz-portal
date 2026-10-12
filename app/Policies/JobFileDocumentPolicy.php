<?php

namespace App\Policies;

use App\Models\JobFile;
use App\Models\JobFileDocument;
use App\Models\User;

/**
 * مستندات ملف الوظيفة: يطّلع عليها من يطّلع على الملف (JobFilePolicy::view). ويضيف المسؤول عن الملف
 * ويعدّل ويحذف كل مستنداته، والمشارك يضيف إن سُمح للمشاركين بالإضافة، ويعدّل ويحذف ما أضافه هو وحده.
 * والمطّلع على كل الملفات (job_files.view) يطّلع ولا يعدّل ما ليس له فيه دور.
 */
class JobFileDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny', JobFile::class);
    }

    public function view(User $user, JobFileDocument $document): bool
    {
        return $document->jobFile !== null && $user->can('view', $document->jobFile);
    }

    public function create(User $user, ?JobFile $file = null): bool
    {
        return $file !== null && ($file->isResponsible($user) || ($file->participants_contribute && $file->isParticipant($user)));
    }

    public function update(User $user, JobFileDocument $document): bool
    {
        $file = $document->jobFile;

        if ($file === null) {
            return false;
        }

        return $file->isResponsible($user)
            || ($file->participants_contribute && $file->isParticipant($user) && $document->created_by === $user->id);
    }

    public function delete(User $user, JobFileDocument $document): bool
    {
        return $this->update($user, $document);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, JobFileDocument $document): bool
    {
        return false;
    }

    public function forceDelete(User $user, JobFileDocument $document): bool
    {
        return false;
    }
}
