<?php

namespace App\Models;

use App\Enums\SurveyAudience;
use Database\Factories\SurveyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * استبيان قياس رضا: لفئة، ومربوط بمسابقة (والبرامج مع وحدتها) أو عام، ورابطه برمز عشوائي.
 * يُحفظ عبر SaveSurvey، وتُجمع إجاباته في SurveyResults.
 *
 * @property SurveyAudience $audience
 * @property int|null $guest_limit سقف إجابات غير المسجلين، وفارغاً لا يجيب غير مسجل
 * @property int $guest_responses
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 */
#[Fillable(['title', 'description', 'audience', 'allow_guests', 'guest_limit', 'opens_at', 'closes_at', 'is_published'])]
class Survey extends Model
{
    /** @use HasFactory<SurveyFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => SurveyAudience::class,
            'allow_guests' => 'boolean',
            'guest_limit' => 'integer',
            'guest_responses' => 'integer',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        static::creating(function (Survey $survey): void {
            $survey->token ??= Str::random(32);
        });
    }

    /**
     * @return HasMany<SurveyQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<SurveyResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isOpen(): bool
    {
        return $this->is_published
            && ! $this->trashed()
            && ($this->opens_at === null || $this->opens_at->isPast())
            && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    /**
     * الاستبيان العام المفتوح (بطاقة «قياس الرضا»): منشور، لفئة «عام»، غير مربوط بمسابقة، مفتوح الآن،
     * والأحدث فتحاً (أو إنشاءً إن لم يُحدد موعد فتحه).
     */
    public static function openGeneral(): ?self
    {
        return self::query()
            ->where('is_published', true)
            ->where('audience', SurveyAudience::General)
            ->whereNull('subject_type')
            ->where(fn (Builder $query) => $query->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->orderByRaw('coalesce(opens_at, created_at) desc')
            ->latest('id')
            ->first();
    }

    /**
     * شرط ظهور بطاقة «قياس الرضا» (config/eservices.php)، فلا تُعرض رابطاً إلى صفحة غير موجودة.
     */
    public static function hasOpenGeneral(): bool
    {
        return self::openGeneral() !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('surveys')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
