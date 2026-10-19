<?php

namespace App\Models;

use App\Enums\StatsAnalysisStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * تقرير تحليل إحصائيات لفترة: الأرقام المجمّعة يبنيها الخادم (StatsAnalysisFigures) وتُعرض بجانب النص، فلا يُعتمد على
 * رقم يكتبه النموذج. ونص التقرير من المزود، بالاشتراك والسقف نفسيهما للمساعد.
 *
 * @property int $id
 * @property int|null $requested_by
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property StatsAnalysisStatus $status
 * @property array<string, array<string, array{label: string, value: int}>> $figures
 * @property string|null $report
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string $cost
 * @property Carbon $created_at
 */
#[Fillable(['requested_by', 'period_from', 'period_to', 'status', 'figures', 'report', 'input_tokens', 'output_tokens', 'cost'])]
class StatsAnalysis extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'status' => StatsAnalysisStatus::class,
            'figures' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
