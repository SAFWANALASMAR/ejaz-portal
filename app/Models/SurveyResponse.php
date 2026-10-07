<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * إجابة على استبيان: {معرّف السؤال: الدرجة أو رقم الخيار أو النص}. والمستخدم لصاحب الحساب فقط،
 * ولا يظهر في النتائج.
 *
 * @property array<mixed> $answers
 * @property Carbon $submitted_at
 */
class SurveyResponse extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * لا تعبئة جماعية: الإجراء يضبط الحقول.
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
            'answers' => 'array',
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

    /**
     * @return BelongsTo<Survey, $this>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }
}
