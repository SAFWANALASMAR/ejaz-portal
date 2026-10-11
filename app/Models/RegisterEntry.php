<?php

namespace App\Models;

use App\Enums\RegisterEntryStatus;
use App\Enums\RegisterEntryType;
use App\Support\Registers;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\RegisterEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * مستند ذو صلاحية في السجلات الداخلية (requirements/13 §1): مستند رسمي أو عقد أو ترخيص أو غيرها، بتاريخ
 * انتهاء اختياري ومدة تنبيه ومسؤول. المتبقي والحالة يُحسبان من التاريخين بتوقيت الرياض ولا يُخزَّنان.
 * والمرفقات في القرص الخاص، وتُنزَّل برابط مؤقت موقّع بعد فحص الصلاحية.
 *
 * @property RegisterEntryType $type
 * @property Carbon $issued_on
 * @property Carbon|null $expires_on
 * @property int $alert_days
 * @property list<array{path: string, name: string, size: int}>|null $attachments
 */
#[Fillable(['type', 'title', 'issuer', 'document_number', 'issued_on', 'expires_on', 'alert_days', 'responsible_id', 'notes'])]
class RegisterEntry extends Model
{
    /** @use HasFactory<RegisterEntryFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public const DIRECTORY = 'register-attachments';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RegisterEntryType::class,
            'issued_on' => 'date',
            'expires_on' => 'date',
            'alert_days' => 'integer',
            'attachments' => 'array',
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

    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /**
     * الأيام المتبقية حتى آخر يوم صالح: صفر في اليوم الأخير، وسالب بعده، وnull لما لا ينتهي.
     */
    public function remainingDays(?CarbonInterface $today = null): ?int
    {
        if ($this->expires_on === null) {
            return null;
        }

        $today = CarbonImmutable::parse(($today ?? Registers::today())->toDateString());

        return (int) round($today->diffInDays(CarbonImmutable::parse($this->expires_on->toDateString()), false));
    }

    public function status(?CarbonInterface $today = null): RegisterEntryStatus
    {
        $remaining = $this->remainingDays($today);

        return match (true) {
            $remaining === null => RegisterEntryStatus::NoExpiry,
            $remaining < 0 => RegisterEntryStatus::Expired,
            $remaining <= $this->alert_days => RegisterEntryStatus::Expiring,
            default => RegisterEntryStatus::Valid,
        };
    }

    /**
     * التصفية بالحالة في القاعدة بمنطق status() نفسه. والتاريخ معامل مربوط لا نص من المستخدم.
     *
     * @param  Builder<RegisterEntry>  $query
     */
    public function scopeWhereStatus(Builder $query, RegisterEntryStatus $status, ?CarbonInterface $today = null): void
    {
        $date = ($today ?? Registers::today())->toDateString();

        match ($status) {
            RegisterEntryStatus::NoExpiry => $query->whereNull('expires_on'),
            RegisterEntryStatus::Expired => $query->whereDate('expires_on', '<', $date),
            RegisterEntryStatus::Expiring => $query->whereDate('expires_on', '>=', $date)->whereRaw('(expires_on - alert_days) <= ?::date', [$date]),
            RegisterEntryStatus::Valid => $query->whereDate('expires_on', '>=', $date)->whereRaw('(expires_on - alert_days) > ?::date', [$date]),
        };
    }

    /**
     * رابط تنزيل مرفق: مؤقت موقّع، ويُفحص الدخول والصلاحية عند الطلب.
     */
    public function attachmentUrl(int $index): string
    {
        return URL::temporarySignedRoute('registers.attachment', now()->addMinutes((int) config('registers.link_minutes')), [$this, $index]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('registers')
            ->logOnly(['type', 'title', 'issuer', 'document_number', 'issued_on', 'expires_on', 'alert_days', 'responsible_id', 'notes'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
