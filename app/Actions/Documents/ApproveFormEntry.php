<?php

namespace App\Actions\Documents;

use App\Enums\ActivityEvent;
use App\Enums\FormEntryStatus;
use App\Models\FormEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * اعتماد نموذج معبأ (عضو المجلس، ولا يعتمده من عبّأه — FormEntryPolicy::approve)، فيُقفل.
 */
class ApproveFormEntry
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, FormEntry $entry): FormEntry
    {
        if (! $actor->can('approve', $entry)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor, $entry): void {
            /** @var FormEntry $locked */
            $locked = FormEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if (! $actor->can('approve', $locked)) {
                throw new AuthorizationException;
            }

            $locked->forceFill(['status' => FormEntryStatus::Approved, 'approved_by' => $actor->id, 'approved_at' => now()])->save();

            activity('documents')->causedBy($actor)->performedOn($locked)
                ->event(ActivityEvent::FormEntryApproved->value)
                ->log(ActivityEvent::FormEntryApproved->value);

            $entry->setRawAttributes($locked->getAttributes(), true);
        });

        return $entry;
    }
}
