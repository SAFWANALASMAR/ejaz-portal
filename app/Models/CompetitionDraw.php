<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
