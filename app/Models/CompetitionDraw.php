<?php

namespace App\Models;

use App\Enums\DrawVoidRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * سجل سحب إلكتروني ثابت: يُنشأ مرة (RunDraw) ولا يُعدَّل ولا يُحذف، في النموذج وفي قاعدة البيانات
 * (مشغّل في الترحيل). ومنه يُعاد السحب للتحقق (ejaz:verify-draw).
 *
 * @property list<string> $entrants
 * @property list<string> $winners
 * @property Carbon $executed_at
 */
class CompetitionDraw extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * لا تعبئة جماعية: الإجراء يضبط الحقول بنفسه.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entrants' => 'array',
            'winners' => 'array',
            'entrants_count' => 'integer',
            'winners_count' => 'integer',
            'executed_at' => 'datetime',
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
        static::updating(fn () => throw new LogicException('A draw record cannot be changed.'));
        static::deleting(fn () => throw new LogicException('A draw record cannot be deleted.'));
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class)->withTrashed();
    }

    /**
     * مرحلة سحب المتساوين في مسابقة الطلاب (RunTieDraw)، أو null لسحب المسابقة كلها.
     *
     * @return BelongsTo<CompetitionRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(CompetitionRound::class, 'round_id');
    }

    /**
     * @return BelongsTo<CompetitionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CompetitionCategory::class, 'category_id');
    }

    /**
     * @return HasOne<CompetitionDrawVoid, $this>
     */
    public function void(): HasOne
    {
        return $this->hasOne(CompetitionDrawVoid::class, 'draw_id');
    }

    /**
     * طلب الإلغاء المعلّق بانتظار قرار مشرف عام ثانٍ (ApproveDrawVoid وRejectDrawVoid)، وهو واحد على الأكثر
     * (فهرس فريد جزئي). والمرفوض والساقط لا يُعدّان، فيُتاح بعدهما طلب جديد.
     *
     * @return HasOne<CompetitionDrawVoidRequest, $this>
     */
    public function voidRequest(): HasOne
    {
        return $this->hasOne(CompetitionDrawVoidRequest::class, 'draw_id')->where('status', DrawVoidRequestStatus::Pending);
    }

    /**
     * الطلب المعلّق الذي لم تمض مدته. والذي مضت مدته كالساقط: لا أزرار له، ويُسقطه أول تعامل مع السحب
     * (LapseDrawVoidRequests::forDraw) قبل الأمر اليومي.
     */
    public function openVoidRequest(): ?CompetitionDrawVoidRequest
    {
        $request = $this->voidRequest;

        return $request !== null && ! $request->hasLapsed() ? $request : null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
