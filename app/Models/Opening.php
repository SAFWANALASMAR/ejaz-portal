<?php

namespace App\Models;

use App\Enums\FormFieldType;
use App\Enums\OpeningKind;
use Database\Factories\OpeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * فرصة يُقدَّم عليها: الانضمام للجمعية العمومية، أو وظيفة، أو فرصة تطوع. حقول نموذجها
 * يحددها المسؤول، لأن الجمعية تعتمدها (requirements/02).
 *
 * @property OpeningKind $kind
 * @property Carbon|null $closes_at
 * @property array<mixed>|null $form_fields
 */
#[Fillable(['kind', 'title', 'slug', 'summary', 'description', 'closes_at', 'is_published', 'form_fields'])]
class Opening extends Model
{
    /** @use HasFactory<OpeningFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => OpeningKind::class,
            'closes_at' => 'datetime',
            'is_published' => 'boolean',
            'form_fields' => 'array',
        ];
    }

    /**
     * @return HasMany<ServiceRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /**
     * @param  Builder<Opening>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * يُقبل التقديم ما دامت منشورة ولم يمض موعد إغلاقها.
     */
    public function acceptsApplications(): bool
    {
        return $this->is_published && ! $this->trashed() && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    /**
     * حقول النموذج بأنواعها.
     *
     * @return list<array{key: string, label: string, type: FormFieldType, required: bool, options: list<string>}>
     */
    public function fields(): array
    {
        return array_values(array_filter(array_map(function (mixed $field): ?array {
            $type = is_array($field) ? FormFieldType::tryFrom((string) ($field['type'] ?? '')) : null;

            if ($type === null || ! is_string($field['key'] ?? null) || ! is_string($field['label'] ?? null)) {
                return null;
            }

            return [
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => $type,
                'required' => (bool) ($field['required'] ?? false),
                'options' => array_values(array_filter((array) ($field['options'] ?? []), 'is_string')),
            ];
        }, $this->form_fields ?? [])));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
