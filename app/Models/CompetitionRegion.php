<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * منطقة في تسجيل مسابقة الطلاب: قائمة من اللوحة يُفعّل منها ما يُراد (مبدئياً مكة المكرمة وحدها).
 */
#[Fillable(['name', 'is_active', 'sort_order'])]
class CompetitionRegion extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * مستعملة في تسجيل، فلا تُحذف (قيد في القاعدة أيضاً)، ويكفي إيقاف تفعيلها.
     */
    public function inUse(): bool
    {
        return CompetitionSubmission::query()->withTrashed()->where('region_id', $this->id)->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
