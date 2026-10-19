<?php

namespace App\Policies;

use App\Enums\MeetingBody;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Support\AssemblyArea;
use Illuminate\Database\Eloquent\Model;

/**
 * اجتماعات الجمعية العمومية (صلاحيات meetings.* وminutes.*).
 *
 * - في اللوحة: السكرتير يدير، وعضو المجلس يطّلع ويعتمد المحاضر. والمجدول وحده يُعدَّل ويُلغى، والمنعقد
 *   سجل لا يُحذف، والمحضر المعتمد لا يُستبدل. وهي موانع صريحة لا يغيّرها Gate::after.
 * - في الموقع: من يصل إلى منطقة الجمعية العمومية (AssemblyArea) يرى اجتماعات الجمعية العمومية وحدها، والمحاضر
 *   المعتمدة وحدها. واجتماعات المجلس واللجان في اللوحة لأعضائها (requirements/13 §2).
 */
class MeetingPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'meetings';
    }

    /**
     * كل جهة يطّلع عليها أعضاؤها، والكل لمن يملك meetings.view_all (requirements/13 §2 — Meeting::isVisibleTo).
     */
    public function view(User $user, Model $record): bool
    {
        /** @var Meeting $record */
        return $record->isVisibleTo($user);
    }

    public function update(User $user, Model $record): bool
    {
        /** @var Meeting $record */
        return $record->status === MeetingStatus::Scheduled && $this->allows($user, 'update') && $record->isVisibleTo($user);
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var Meeting $record */
        return $record->status !== MeetingStatus::Held && $this->allows($user, 'delete') && $record->isVisibleTo($user);
    }

    public function cancel(User $user, Meeting $meeting): bool
    {
        return $meeting->status === MeetingStatus::Scheduled && $this->allows($user, 'update');
    }

    /**
     * إعادة مزامنة تيمز بأي حال للاجتماع: الملغى قد يبقى له اجتماع تيمز تعذّر إلغاؤه.
     */
    public function syncOnline(User $user, Meeting $meeting): bool
    {
        return $this->allows($user, 'update') && $meeting->isVisibleTo($user);
    }

    /**
     * الانعقاد والحضور بعد بدء الموعد.
     */
    public function markHeld(User $user, Meeting $meeting): bool
    {
        return $meeting->status === MeetingStatus::Scheduled && $meeting->starts_at->isPast() && $this->allows($user, 'update');
    }

    public function recordAttendance(User $user, Meeting $meeting): bool
    {
        return $meeting->status !== MeetingStatus::Cancelled && $meeting->starts_at->isPast() && $this->allows($user, 'update') && $meeting->isVisibleTo($user);
    }

    /**
     * محضر اجتماع منعقد، ما لم يُعتمد.
     */
    public function uploadMinutes(User $user, Meeting $meeting): bool
    {
        return $meeting->status === MeetingStatus::Held && ! $meeting->hasApprovedMinutes() && $user->can('minutes.create') && $meeting->isVisibleTo($user);
    }

    /**
     * فصل المهام: لا يعتمد المحضر من رفعه.
     */
    public function approveMinutes(User $user, Meeting $meeting): bool
    {
        return $meeting->hasDraftMinutes() && $meeting->minutes_uploaded_by !== $user->id && $user->can('minutes.approve') && $meeting->isVisibleTo($user);
    }

    /**
     * صفحة الاجتماع في منطقة الأعضاء.
     */
    public function attend(User $user, Meeting $meeting): bool
    {
        return $meeting->body === MeetingBody::GeneralAssembly && AssemblyArea::allows($user);
    }

    public function readMinutes(User $user, Meeting $meeting): bool
    {
        return $meeting->body === MeetingBody::GeneralAssembly && $meeting->hasApprovedMinutes() && AssemblyArea::allows($user);
    }
}
