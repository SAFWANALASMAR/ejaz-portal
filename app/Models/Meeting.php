<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Jobs\SyncOnlineMeeting;
use App\Support\AssemblyArea;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * اجتماع للجمعية العمومية بجدول أعماله ومرفقاته، ومكانه أو رابط تيمز، وحضوره ونصابه، ومحضره (مسودة
 * ثم معتمد). يُطلب في الروابط بمعرّفه uuid. والملفات في القرص الخاص، ورابط تيمز مشفّر لا يظهر إلا
 * للأعضاء.
 *
 * @property MeetingType $type
 * @property MeetingStatus $status
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property list<array{path: string, name: string, size: int}>|null $attachments
 * @property Carbon|null $minutes_approved_at
 */
#[Fillable(['title', 'type', 'starts_at', 'ends_at', 'location', 'is_online', 'agenda', 'attachments', 'quorum_percent'])]
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public const ATTACHMENTS_DIRECTORY = 'meeting-attachments';

    public const MINUTES_DIRECTORY = 'meeting-minutes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MeetingType::class,
            'status' => MeetingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_online' => 'boolean',
            'online_join_url' => 'encrypted',
            'attachments' => 'array',
            'quorum_percent' => 'integer',
            'members_count' => 'integer',
            'minutes_approved_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        // المحذوف من اللوحة يُلغى اجتماع تيمز له في الطابور، والمستعاد يُنشأ له من جديد
        static::deleted(function (Meeting $meeting): void {
            if ($meeting->online_meeting_id !== null) {
                SyncOnlineMeeting::dispatch($meeting->id);
            }
        });

        static::restored(function (Meeting $meeting): void {
            if ($meeting->is_online) {
                SyncOnlineMeeting::dispatch($meeting->id);
            }
        });
    }

    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_attendances')->withPivot('recorded_by', 'created_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function minutesUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'minutes_uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function minutesApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'minutes_approved_by');
    }

    /**
     * رابط تنزيل مرفق في منطقة الأعضاء: مؤقت موقّع (AssemblyArea::LINK_MINUTES).
     */
    public function attachmentUrl(int $index): string
    {
        return URL::temporarySignedRoute('assembly.meetings.attachment', now()->addMinutes(AssemblyArea::LINK_MINUTES), [$this, $index]);
    }

    public function minutesUrl(): string
    {
        return URL::temporarySignedRoute('assembly.meetings.minutes', now()->addMinutes(AssemblyArea::LINK_MINUTES), [$this]);
    }

    public function hasDraftMinutes(): bool
    {
        return $this->minutes_file !== null && $this->minutes_approved_at === null;
    }

    public function hasApprovedMinutes(): bool
    {
        return $this->minutes_file !== null && $this->minutes_approved_at !== null;
    }

    /**
     * النصاب بعد تسجيل الحضور: الحاضرون من عدد الأعضاء عند التسجيل، بالتقريب للأعلى. وnull إن لم تُحدد
     * نسبته أو لم يُسجَّل الحضور بعد.
     */
    public function quorumMet(int $attendees): ?bool
    {
        if ($this->quorum_percent === null || $this->members_count === null) {
            return null;
        }

        return $attendees >= (int) ceil($this->members_count * $this->quorum_percent / 100);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // جدول الأعمال والمرفقات والرابط لا تدخل السجل: نص طويل وملفات ورابط سري
        return LogOptions::defaults()->useLogName('assembly')
            ->logOnly(['title', 'type', 'starts_at', 'ends_at', 'location', 'is_online', 'quorum_percent', 'status'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
