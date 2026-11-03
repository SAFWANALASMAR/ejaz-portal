<?php

namespace App\Models;

use App\Enums\MeetingBody;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\OnlineMeetingFailure;
use App\Jobs\SyncOnlineMeeting;
use App\Support\AssemblyArea;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property MeetingBody $body
 * @property int|null $committee_id
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property list<array{path: string, name: string, size: int}>|null $attachments
 * @property OnlineMeetingFailure|null $online_sync_error
 * @property Carbon|null $online_sync_failed_at
 * @property Carbon|null $minutes_approved_at
 */
#[Fillable(['title', 'type', 'starts_at', 'ends_at', 'location', 'is_online', 'agenda', 'attachments', 'quorum_percent', 'body', 'committee_id'])]
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public const ATTACHMENTS_DIRECTORY = 'meeting-attachments';

    /**
     * الجهة الافتراضية كما في القاعدة، فتصح للنموذج قبل قراءته منها.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['body' => 'general_assembly', 'online_generation' => 0, 'online_create_pending' => false, 'online_join_manual' => false];

    public const MINUTES_DIRECTORY = 'meeting-minutes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MeetingType::class,
            'status' => MeetingStatus::class,
            'body' => MeetingBody::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_online' => 'boolean',
            'online_join_url' => 'encrypted',
            'online_join_manual' => 'boolean',
            'online_sync_error' => OnlineMeetingFailure::class,
            'online_sync_failed_at' => 'datetime',
            'online_generation' => 'integer',
            'online_create_pending' => 'boolean',
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
            if ($meeting->hasOnlineMeeting() || $meeting->online_sync_error !== null) {
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
     * @return BelongsTo<Committee, $this>
     */
    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class)->withTrashed();
    }

    /**
     * @return HasMany<MeetingAgendaItem, $this>
     */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(MeetingAgendaItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * اجتماعات الجمعية العمومية وحدها (منطقة الأعضاء في الموقع ودعواتها).
     *
     * @param  Builder<Meeting>  $query
     */
    public function scopeGeneralAssembly(Builder $query): void
    {
        $query->where('body', MeetingBody::GeneralAssembly->value);
    }

    /**
     * ما يطّلع عليه الحساب في اللوحة (requirements/13 §2): الكل لمن يملك meetings.view_all، ولغيره ممن يملك
     * meetings.view اجتماعات الجمعية العمومية والمجلس ولجانه التي هو عضو فعّال فيها.
     *
     * @param  Builder<Meeting>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('meetings.view')) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->can('meetings.view_all')) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereIn('body', [MeetingBody::GeneralAssembly->value, MeetingBody::Board->value])
            ->orWhereIn('committee_id', BodyMembership::query()->active()->where('user_id', $user->id)->ofBody(MeetingBody::Committee)->select('committee_id')));
    }

    public function isVisibleTo(User $user): bool
    {
        if (! $user->can('meetings.view')) {
            return false;
        }

        return $user->can('meetings.view_all')
            || $this->body !== MeetingBody::Committee
            || BodyMembership::query()->active()->where('user_id', $user->id)->ofBody(MeetingBody::Committee, $this->committee_id)->exists();
    }

    /**
     * من يُسجَّل حضوره ويُحسب منه النصاب: الأعضاء الفعّالون في جهة الاجتماع.
     *
     * @return Builder<User>
     */
    public function eligibleAttendees(): Builder
    {
        return $this->body === MeetingBody::GeneralAssembly
            ? AssemblyMembership::activeUsers()
            : BodyMembership::activeUsers($this->body, $this->committee_id);
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

    /**
     * يلزمه اجتماع تيمز: عن بعد، ولم يُلغَ ولم يُحذف.
     */
    public function wantsOnlineMeeting(): bool
    {
        // الرابط اليدوي يغني عن تيمز: لا يُنشأ له اجتماع، ويُلغى ما كان له قبله
        return $this->is_online && ! $this->online_join_manual && $this->status !== MeetingStatus::Cancelled && ! $this->trashed();
    }

    /**
     * تيمز لا يطابق الاجتماع: تعذّرت مزامنته، أو يلزمه رابط لم يُنشأ، أو بقي له اجتماع تيمز لا يلزمه (ومنه
     * إنشاء لم يُعرف مصيره). فتظهر إعادة المحاولة للمسؤول.
     */
    public function needsOnlineSync(): bool
    {
        return $this->online_sync_error !== null
            || ($this->wantsOnlineMeeting() ? $this->online_meeting_id === null : $this->hasOnlineMeeting());
    }

    /**
     * له اجتماع تيمز، أو قد يكون: طُلب إنشاؤه ولم يُعرف معرّفه.
     */
    public function hasOnlineMeeting(): bool
    {
        return $this->online_meeting_id !== null || $this->online_create_pending;
    }

    public function getActivitylogOptions(): LogOptions
    {
        // جدول الأعمال والمرفقات والرابط لا تدخل السجل: نص طويل وملفات ورابط سري
        return LogOptions::defaults()->useLogName('assembly')
            ->logOnly(['title', 'type', 'body', 'committee_id', 'starts_at', 'ends_at', 'location', 'is_online', 'quorum_percent', 'status'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
