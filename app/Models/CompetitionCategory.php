<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * فئة في مسابقة الطلاب (المرحلة الدراسية): قائمة عامة من اللوحة، ولكل فئة صفوفها. وتُفتح لكل مسابقة
 * فئاتها (competition_category_competition).
 *
 * @property array<mixed>|null $grades
 */
#[Fillable(['name', 'grades', 'sort_order'])]
class CompetitionCategory extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grades' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function gradeList(): array
    {
        return array_values(array_filter((array) $this->grades, fn ($grade): bool => is_string($grade) && trim($grade) !== ''));
    }

    /**
     * مستعملة في تسجيل أو سؤال أو نص أو مسابقة، فلا تُحذف (قيد في القاعدة أيضاً).
     */
    public function inUse(): bool
    {
        return CompetitionSubmission::query()->withTrashed()->where('category_id', $this->id)->exists()
            || CompetitionQuestion::query()->where('category_id', $this->id)->exists()
            || CompetitionPassage::query()->where('category_id', $this->id)->exists()
            || DB::table('competition_category_competition')->where('category_id', $this->id)->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
