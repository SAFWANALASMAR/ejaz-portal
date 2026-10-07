<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * طلب إلكتروني على فرصة. يظهر برقمه المرجعي، ويُطلب في الروابط بمعرّفه uuid، فلا يُخمَّن.
 * والمرفقات في القرص الخاص، ومساراتها في files.
 *
 * @property ServiceRequestStatus $status
 * @property array<string, mixed>|null $answers
 * @property array<string, array{path: string, name: string, size: int}>|null $files
 * @property Carbon|null $submitted_at
 */
#[Fillable(['opening_id', 'user_id', 'status', 'answers', 'files', 'assigned_to', 'submitted_at'])]
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public const FILE_DIRECTORY = 'service-requests';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ServiceRequestStatus::class,
            'answers' => 'array',
            'files' => 'array',
            'submitted_at' => 'datetime',
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
        static::creating(function (ServiceRequest $request): void {
            $request->reference ??= self::newReference();
        });
    }

    /**
     * رقم مرجعي عشوائي من عشرة حروف وأرقام.
     */
    public static function newReference(): string
    {
        do {
            $reference = Str::upper(Str::random(10));
        } while (self::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * @return BelongsTo<Opening, $this>
     */
    public function opening(): BelongsTo
    {
        return $this->belongsTo(Opening::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<ServiceRequestEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ServiceRequestEvent::class)->orderBy('id');
    }

    /**
     * ما أُرسل للجمعية، بلا المسودات التي لا يراها غير صاحبها.
     *
     * @param  Builder<ServiceRequest>  $query
     */
    public function scopeSubmitted(Builder $query): void
    {
        $query->where('status', '!=', ServiceRequestStatus::Draft->value);
    }

    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    /**
     * السجل للحالة والإسناد فقط، لا لإجابات المتقدم ولا مرفقاته (بيانات شخصية).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'assigned_to'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
