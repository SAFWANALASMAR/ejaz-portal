<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Database\Factories\ProgramRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * تسجيل مستخدم في برنامج. واحد لكل مستخدم في البرنامج، والملغى يُعاد تفعيله إن سجّل من جديد.
 * يُطلب في الروابط بمعرّفه uuid.
 *
 * @property RegistrationStatus $status
 * @property array<string, mixed>|null $answers
 * @property Carbon|null $attended_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $started_at
 * @property Carbon|null $opened_at
 * @property string|null $pages_seen
 * @property int $pages_count
 * @property int $seconds_baseline
 * @property Carbon|null $reminded_at
 * @property Carbon|null $cancelled_at
 * @property string|null $certificate_path شهادة الحضور في القرص الخاص (SyncAttendanceCertificate)
 * @property Carbon|null $certificate_issued_at
 * @property Carbon|null $waitlisted_at ترتيب قائمة الانتظار: الأقدم يُرقّى أولاً
 */
#[Fillable(['status', 'answers', 'attended_at', 'reminded_at', 'cancelled_at', 'waitlisted_at'])]
class ProgramRegistration extends Model
{
    /** @use HasFactory<ProgramRegistrationFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'answers' => 'array',
            'attended_at' => 'datetime',
            'completed_at' => 'datetime',
            'started_at' => 'datetime',
            'opened_at' => 'datetime',
            'pages_count' => 'integer',
            'seconds_baseline' => 'integer',
            'reminded_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'waitlisted_at' => 'datetime',
            'certificate_issued_at' => 'datetime',
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

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * السجل للحالة والحضور فقط، لا لإجابات المسجل.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'attended_at', 'completed_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
