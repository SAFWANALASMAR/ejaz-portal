<?php

namespace App\Models;

use App\Enums\CompetitionFieldType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * حقل تسجيل مخصص في مسابقة الطلاب (نص، أو رقم، أو اختيار)، يُضاف من اللوحة بلا برمجة. وقيمته في
 * competition_submissions.extra بمعرّفه.
 *
 * @property CompetitionFieldType $type
 * @property array<mixed>|null $options
 */
#[Fillable(['label', 'type', 'options', 'is_required', 'sort_order'])]
class CompetitionField extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CompetitionFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return list<string>
     */
    public function optionList(): array
    {
        return array_values(array_filter((array) $this->options, fn ($option): bool => is_string($option) && trim($option) !== ''));
    }

    public function key(): string
    {
        return 'field_'.$this->id;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logOnly(['competition_id', 'label', 'type', 'options', 'is_required', 'sort_order'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
