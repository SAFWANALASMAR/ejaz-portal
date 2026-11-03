<?php

namespace App\Models;

use Database\Factories\EvidenceGalleryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * معرض شواهد يُعرض لمانح أو مسؤول برابط سري بلا حساب (docs/decisions.md — 30 أكتوبر 2026). بنوده إشارات إلى
 * ألبومات وعناصر أرشيف موجودة، والرمز لا يُحفظ بل بصمته، ويُصدر ويُلغى من اللوحة (IssueEvidenceLink وRevokeEvidenceLink).
 *
 * @property ?string $token_hash
 * @property ?Carbon $token_issued_at
 * @property ?int $token_issued_by
 * @property ?Carbon $expires_at
 * @property ?Carbon $revoked_at
 * @property ?Carbon $period_from
 * @property ?Carbon $period_to
 */
#[Fillable(['title', 'description', 'program_id', 'period_from', 'period_to'])]
class EvidenceGallery extends Model
{
    /** @use HasFactory<EvidenceGalleryFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public const MAX_ITEMS = 100;

    /**
     * طول الرمز: 48 حرفاً وأرقاماً (نحو 285 بتاً)، فلا يُخمَّن.
     */
    public const TOKEN_LENGTH = 48;

    /**
     * مدة الرابط بالأيام: الافتراضية في نموذج الإصدار، والقصوى من وقت الإصدار. ولا رابط بلا انتهاء
     * (المراجعة الأمنية بعد v0.14.2، docs/decisions/2026-11-03-security-lows.md).
     */
    public const DEFAULT_LINK_DAYS = 90;

    public const MAX_LINK_DAYS = 365;

    /**
     * أقصى انتهاء لرابط يُصدر الآن: آخر اليوم الـ365 بتوقيت الرياض، فيتفق منتقي اللوحة (باليوم) والإجراء (بالساعة)،
     * ولا يُرفض وقت يعرضه المنتقي في ذلك اليوم (مراجعة المشرف على #137).
     */
    public static function latestExpiry(): Carbon
    {
        return now(config('app.display_timezone'))->addDays(self::MAX_LINK_DAYS)->endOfDay();
    }

    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'token_issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    protected static function booted(): void
    {
        // حذف المعرض يلغي رابطه، فلا يعود الرابط القديم إن استُعيد المعرض: يلزمه رابط جديد
        static::deleted(function (EvidenceGallery $gallery): void {
            $gallery->newQueryWithoutScopes()->whereKey($gallery->getKey())->whereNull('revoked_at')->update(['revoked_at' => now()]);
        });
    }

    /**
     * المعرض الذي رمزه هذا ورابطه ساري: صادر، وغير ملغى، وله انتهاء لم يحل (فما بقي بلا انتهاء غير ساري)، وغير محذوف، ومُصدره حساب فعّال. والبحث ببصمة
     * الرمز لا بالرمز نفسه، فما يُقارن في القاعدة بصمة لا تكشف شيئاً عن رمز صحيح.
     */
    public static function findActiveByToken(string $token): ?self
    {
        if (preg_match('/^[A-Za-z0-9]{'.self::TOKEN_LENGTH.'}$/', $token) !== 1) {
            return null;
        }

        return self::query()->active()->where('token_hash', self::hashToken($token))->first();
    }

    /**
     * في المعرض عنصر أرشيف (ولو محذوفاً ناعماً) من تصنيف لا يطّلع عليه الحساب.
     */
    public function hasArchiveHiddenFrom(User $user): bool
    {
        return ArchiveItem::withTrashed()
            ->whereIn('id', $this->items()->getQuery()->toBase()->select('archive_item_id')->whereNotNull('archive_item_id'))
            ->whereNotIn('archive_category_id', ArchiveCategory::visibleTo($user)->select('id'))
            ->exists();
    }

    /**
     * @param  Builder<EvidenceGallery>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotNull('token_hash')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->whereHas('issuer', fn (Builder $query) => $query->where('is_active', true));
    }

    public function linkIsActive(): bool
    {
        return $this->token_hash !== null && $this->revoked_at === null && $this->expires_at?->isFuture() === true && ! $this->trashed()
            && $this->issuer?->is_active === true;
    }

    /**
     * مفتاح إصدار الرابط في روابط الملفات الموقّعة: يتغير بكل إصدار، فلا يبقى رابط ملف من إصدار سابق صالحاً.
     */
    public function linkKey(): string
    {
        return substr((string) $this->token_hash, 0, 16);
    }

    /**
     * @return HasMany<EvidenceGalleryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EvidenceGalleryItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<EvidenceGalleryVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(EvidenceGalleryVisit::class);
    }

    /**
     * من أصدر الرابط الحالي: لا يُعرض فيه من الأرشيف إلا ما يطّلع عليه هو الآن، وتعطيل حسابه يوقف الرابط.
     *
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'token_issued_by');
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('documents')
            ->logOnly(['title', 'description', 'program_id', 'period_from', 'period_to'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
