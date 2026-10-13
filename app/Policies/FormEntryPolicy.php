<?php

namespace App\Policies;

use App\Enums\FormAudience;
use App\Enums\FormEntryStatus;
use App\Models\FormEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * النماذج المعبأة (صلاحيات form_entries.*). المسودة تُعدَّل وتُحذف، والمعتمد يُقفل (منع صريح لا يغيّره
 * Gate::after). ويعتمد المجلس، ولا يعتمد المعبأ من عبّأه.
 */
class FormEntryPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'form_entries';
    }

    public function update(User $user, Model $record): bool
    {
        /** @var FormEntry $record */
        return $record->status === FormEntryStatus::Draft && $this->allows($user, 'update');
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var FormEntry $record */
        return $record->status === FormEntryStatus::Draft && $this->allows($user, 'delete');
    }

    public function approve(User $user, FormEntry $entry): bool
    {
        return $entry->status === FormEntryStatus::Draft && $entry->created_by !== $user->id && $this->allows($user, 'approve');
    }

    /**
     * صاحب المعبأ في لوحته: المعتمد من نماذج المشاركين المحفوظة في ملفه وحده. ونماذج الموظفين داخلية.
     */
    public function viewAsPerson(User $user, FormEntry $entry): bool
    {
        return $entry->person_id === $user->id
            && $entry->status === FormEntryStatus::Approved
            && $entry->version->template->audience === FormAudience::Participants;
    }

    /**
     * تصدير المعبأ: بيانات قد تكون شخصية تخرج من اللوحة، فلها صلاحيتها.
     */
    public function export(User $user): bool
    {
        return $this->allows($user, 'export');
    }
}
