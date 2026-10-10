<?php

namespace App\Models;

use App\Enums\ProgramDelivery;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\Concerns\DefinesFormFields;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Contracts\HasFormFields;
use App\Models\Contracts\HasPublicImages;
use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * برنامج أو دورة (requirements/03): مواعيده وطريقة تنفيذه ومقاعده وفترة تسجيله، وحقول إضافية
 * يحددها المسؤول. رابط الاجتماع لا يظهر إلا للمسجلين المؤكدين. والصورة على القرص العام.
 *
 * @property ProgramDelivery $delivery
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $registration_opens_at
 * @property Carbon|null $registration_closes_at
 * @property array<mixed>|null $form_fields
 * @property int|null $confirmed_count
 */
#[Fillable([
    'program_type_id', 'initiative_id', 'slug', 'title', 'summary', 'description', 'image', 'starts_at', 'ends_at',
    'delivery', 'location', 'meeting_url', 'seats', 'audience', 'conditions', 'registration_opens_at',
    'registration_closes_at', 'has_waitlist', 'form_fields', 'is_published',
])]
class Program extends Model implements HasFormFields, HasPublicImages
{
    /** @use HasFactory<ProgramFactory> */
    use DefinesFormFields, FlushesContentCache, HasFactory, LogsActivity, SoftDeletes;

    public const IMAGE_DIRECTORY = 'programs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery' => ProgramDelivery::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'seats' => 'integer',
            'has_waitlist' => 'boolean',
            'form_fields' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProgramType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ProgramType::class, 'program_type_id');
    }

    /**
     * @return BelongsTo<Initiative, $this>
     */
    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }

    /**
     * @return HasMany<ProgramRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(ProgramRegistration::class);
    }

    /**
     * @param  Builder<Program>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * عدد المؤكدين في confirmed_count، فتُحسب الحالة لقائمة كاملة باستعلام واحد.
     *
     * @param  Builder<Program>  $query
     */
    public function scopeWithConfirmedCount(Builder $query): void
    {
        $query->withCount(['registrations as confirmed_count' => fn (Builder $registrations) => $registrations->where('status', RegistrationStatus::Confirmed->value)]);
    }

    public function confirmedCount(): int
    {
        return $this->confirmed_count ?? $this->registrations()->where('status', RegistrationStatus::Confirmed->value)->count();
    }

    public function hasFreeSeat(?int $confirmed = null): bool
    {
        return $this->seats === null || ($confirmed ?? $this->confirmedCount()) < $this->seats;
    }

    /**
     * التسجيل مفتوح: منشور، وبدأت فترة التسجيل ولم تنته، ولم يبدأ البرنامج.
     */
    public function registrationIsOpen(): bool
    {
        $now = now();

        return $this->is_published
            && ! $this->trashed()
            && ($this->registration_opens_at === null || $this->registration_opens_at->lte($now))
            && ($this->registration_closes_at === null || $this->registration_closes_at->gt($now))
            && $this->starts_at->gt($now);
    }

    /**
     * الحالة المعروضة: منتهٍ بعد انتهائه، وقريباً قبل فتح التسجيل، ومكتمل إذا امتلأ بلا قائمة
     * انتظار أو أُغلق تسجيله، ومفتوح فيما سوى ذلك.
     */
    public function status(): ProgramStatus
    {
        $now = now();

        if (($this->ends_at ?? $this->starts_at)->lte($now)) {
            return ProgramStatus::Ended;
        }

        if ($this->registration_opens_at !== null && $this->registration_opens_at->gt($now)) {
            return ProgramStatus::Soon;
        }

        if (! $this->registrationIsOpen() || (! $this->has_waitlist && ! $this->hasFreeSeat())) {
            return ProgramStatus::Full;
        }

        return ProgramStatus::Open;
    }

    public function imageDirectory(): string
    {
        return self::IMAGE_DIRECTORY;
    }

    /**
     * @return array<string, list<string>>
     */
    public function publicImages(): array
    {
        return ['image' => is_string($this->image) && $this->image !== '' ? [$this->image] : []];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
