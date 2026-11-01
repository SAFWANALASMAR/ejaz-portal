<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Support\AssemblyMembersCount;
use Database\Factories\AssemblyMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * عضوية حساب في الجمعية العمومية (سجل الأعضاء). تُحفظ عبر SaveAssemblyMembership وحده، فيتبعها دور
 * عضو الجمعية العمومية: الفعّالة تمنحه، وغيرها تسحبه.
 *
 * @property MembershipStatus $status
 * @property Carbon|null $joined_on
 */
#[Fillable(['user_id', 'status', 'joined_on', 'service_request_id'])]
class AssemblyMembership extends Model
{
    /** @use HasFactory<AssemblyMembershipFactory> */
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        // العدد العام في صفحة الحوكمة (AssemblyMembersCount)
        static::saved(fn () => AssemblyMembersCount::forget());
        static::deleted(fn () => AssemblyMembersCount::forget());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'joined_on' => 'date',
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
     * طلب الانضمام المقبول الذي نشأت منه العضوية، إن وُجد.
     *
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class)->withTrashed();
    }

    /**
     * @param  Builder<AssemblyMembership>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', MembershipStatus::Active);
    }

    /**
     * حسابات الأعضاء الفعّالين (للدعوات والحضور): عضوية فعّالة، وحساب فعّال لم تُخفَ بياناته.
     *
     * @return Builder<User>
     */
    public static function activeUsers(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereNull('anonymized_at')
            ->whereIn('id', self::query()->active()->select('user_id'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('assembly')->logOnly(['user_id', 'status', 'joined_on'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
