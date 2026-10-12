<?php

namespace App\Models;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use Database\Factories\AnnualPlanItemFactory;
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
 * بند في الخطة السنوية للبرامج (requirements/13 §3). يُربط بالبرنامج المنفذ في وحدة البرامج، فيُحسب
 * المنفَّذ والمستفيدون الفعليون آلياً ولا يُخزَّنان.
 *
 * @property int $year
 * @property int|null $target_beneficiaries
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 */
#[Fillable([
    'year', 'department', 'type', 'title', 'objective_breakdown', 'estimated_budget', 'supporting_entities', 'target_group',
    'target_beneficiaries', 'preparation_period', 'starts_on', 'ends_on', 'approvals', 'program_id', 'notes',
])]
class AnnualPlanItem extends Model
{
    /** @use HasFactory<AnnualPlanItemFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'estimated_budget' => 'decimal:2',
            'target_beneficiaries' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * البرنامج المربوط ولو حُذف ناعماً، فيظهر أنه لم يعد منفذاً بدل أن يختفي الربط.
     *
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    /**
     * عدد الحاضرين والمؤكدين للبرنامج المربوط باستعلام واحد لقائمة كاملة.
     *
     * @param  Builder<AnnualPlanItem>  $query
     */
    public function scopeWithOutcome(Builder $query): void
    {
        $query->with(['program' => fn ($program) => $program->withCount([
            'registrations as attended_count' => fn (Builder $registrations) => $registrations->whereNotNull('attended_at'),
            'registrations as confirmed_count' => fn (Builder $registrations) => $registrations->where('status', RegistrationStatus::Confirmed->value),
        ])]);
    }

    /**
     * منفّذ: مربوط ببرنامج منشور غير محذوف انتهى موعده.
     */
    public function isExecuted(): bool
    {
        $program = $this->program;

        return $program !== null && ! $program->trashed() && $program->is_published && $program->status() === ProgramStatus::Ended;
    }

    /**
     * المستفيدون الفعليون: من سُجّل حضوره، فإن لم يُسجَّل حضور فالتسجيلات المؤكدة.
     */
    public function actualBeneficiaries(): int
    {
        $program = $this->program;

        if ($program === null) {
            return 0;
        }

        $attended = $program->getAttributes()['attended_count'] ?? $program->registrations()->whereNotNull('attended_at')->count();

        return (int) $attended > 0 ? (int) $attended : $program->confirmedCount();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('registers')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
