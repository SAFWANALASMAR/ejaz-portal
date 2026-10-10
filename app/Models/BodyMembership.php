<?php

namespace App\Models;

use App\Enums\BodyRole;
use App\Enums\MeetingBody;
use App\Support\Registers;
use Database\Factories\BodyMembershipFactory;
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
 * عضوية حساب في مجلس الإدارة أو في لجنة، بصفته ومدة عضويته (requirements/13 §2). والفعّالة ما كان اليوم
 * (بتوقيت الرياض) ضمن مدتها. وعضوية الجمعية العمومية في سجلها (AssemblyMembership).
 *
 * @property MeetingBody $body
 * @property BodyRole $role
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 */
#[Fillable(['body', 'committee_id', 'user_id', 'role', 'starts_on', 'ends_on', 'notes'])]
class BodyMembership extends Model
{
    /** @use HasFactory<BodyMembershipFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body' => MeetingBody::class,
            'role' => BodyRole::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Committee, $this>
     */
    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    /**
     * @param  Builder<BodyMembership>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $today = Registers::today()->toDateString();

        $query->whereDate('starts_on', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
    }

    /**
     * @param  Builder<BodyMembership>  $query
     */
    public function scopeOfBody(Builder $query, MeetingBody $body, ?int $committeeId = null): void
    {
        $query->where('body', $body->value)->when($body === MeetingBody::Committee, fn (Builder $query) => $query->where('committee_id', $committeeId));
    }

    public function isActive(): bool
    {
        $today = Registers::today()->toDateString();

        return $this->starts_on->toDateString() <= $today && ($this->ends_on === null || $this->ends_on->toDateString() >= $today);
    }

    /**
     * الحسابات الفعّالة ذات العضوية الفعّالة في الجهة.
     *
     * @return Builder<User>
     */
    public static function activeUsers(MeetingBody $body, ?int $committeeId = null): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereNull('anonymized_at')
            ->whereIn('id', self::query()->active()->ofBody($body, $committeeId)->select('user_id'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('registers')->logOnly(['body', 'committee_id', 'user_id', 'role', 'starts_on', 'ends_on'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
