<?php

namespace App\Models;

use App\Enums\AgendaItemStatus;
use Database\Factories\MeetingAgendaItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * بند في جدول أعمال اجتماع بقراره ومكلَّفه وموعده وحالة تنفيذه (requirements/13 §2). يغذي ملف «القرارات
 * والتكليفات» في ملفات الوظائف (assignedTo).
 *
 * @property AgendaItemStatus $status
 * @property Carbon|null $due_on
 */
#[Fillable(['position', 'title', 'decision', 'assignee_id', 'due_on', 'status'])]
class MeetingAgendaItem extends Model
{
    /** @use HasFactory<MeetingAgendaItemFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'due_on' => 'date',
            'status' => AgendaItemStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @param  Builder<MeetingAgendaItem>  $query
     */
    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $query->where('assignee_id', $user->id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('assembly')->logOnly(['meeting_id', 'position', 'title', 'decision', 'assignee_id', 'due_on', 'status'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
