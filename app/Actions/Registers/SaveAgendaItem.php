<?php

namespace App\Actions\Registers;

use App\Enums\AgendaItemStatus;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ بند في جدول أعمال اجتماع بقراره ومكلَّفه وموعده وحالة تنفيذه (السكرتير — MeetingAgendaItemPolicy).
 * والبند لا ينتقل إلى اجتماع آخر. ويُسجَّل الحفظ.
 */
class SaveAgendaItem
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Meeting $meeting, MeetingAgendaItem $item, array $data): MeetingAgendaItem
    {
        $allowed = $item->exists
            ? $item->meeting_id === $meeting->id && $actor->can('update', $item)
            : $actor->can('create', MeetingAgendaItem::class);

        if (! $allowed || ! $meeting->isVisibleTo($actor)) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'position' => ['required', 'integer', 'between:1,999'],
            'title' => ['required', 'string', 'max:300'],
            'decision' => ['nullable', 'string', 'max:5000'],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'due_on' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(AgendaItemStatus::class)],
        ])->validate();

        $item->fill([
            'position' => (int) $validated['position'],
            'title' => $validated['title'],
            'decision' => $validated['decision'] ?? null,
            'assignee_id' => $validated['assignee_id'] ?? null,
            'due_on' => $validated['due_on'] ?? null,
            'status' => $validated['status'] ?? AgendaItemStatus::NotStarted->value,
        ]);
        $item->meeting_id = $meeting->id;
        $item->save();

        return $item;
    }
}
