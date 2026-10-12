<?php

namespace App\Actions\Competitions;

use App\Models\SubmissionWork;
use App\Models\User;
use App\Support\SubmissionLock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * حذف عمل من المشاركة ما دامت مسودة والمسابقة مفتوحة، بقفل صفها. والملف يُحذف بعد اعتماد الحذف.
 */
class RemoveSubmissionWork
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $user, SubmissionWork $work): void
    {
        $submission = $work->submission;

        if ($submission === null || ! $user->can('update', $submission)) {
            throw new AuthorizationException;
        }

        if (! $submission->competition->isOpen()) {
            throw new AuthorizationException(__('competitions.entry.closed'));
        }

        DB::transaction(function () use ($submission, $work): void {
            SubmissionLock::draft($submission->id);
            $work->delete();
        });

        if (filled($work->path) && ! Storage::disk(SubmissionWork::disk())->delete((string) $work->path)) {
            Log::warning('Could not delete a removed competition work file.', ['work' => $work->uuid]);
        }
    }
}
