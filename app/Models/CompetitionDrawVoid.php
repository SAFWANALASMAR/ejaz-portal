<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * إلغاء سحب: سجل ثابت بسببه ومنفّذه ووقته (VoidDraw). والسحب الملغى يبقى ظاهراً معلَّماً.
 */
class CompetitionDrawVoid extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['voided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A draw void cannot be changed.'));
        static::deleting(fn () => throw new LogicException('A draw void cannot be deleted.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
