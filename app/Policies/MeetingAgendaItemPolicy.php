<?php

namespace App\Policies;

use App\Models\MeetingAgendaItem;
use App\Models\User;

/**
 * بنود جدول الأعمال وقراراتها (requirements/13 §2): يطّلع عليها من يرى اجتماعها، ويديرها السكرتير
 * (meetings.update) في أي حال للاجتماع، لأن القرارات وتنفيذها تُسجَّل بعد الانعقاد.
 */
class MeetingAgendaItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('meetings.view');
    }

    public function view(User $user, MeetingAgendaItem $item): bool
    {
        return $item->meeting !== null && $item->meeting->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('meetings.update');
    }

    public function update(User $user, MeetingAgendaItem $item): bool
    {
        return $user->can('meetings.update');
    }

    public function delete(User $user, MeetingAgendaItem $item): bool
    {
        return $user->can('meetings.update');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('meetings.update');
    }
}
