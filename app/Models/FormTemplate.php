<?php

namespace App\Models;

use Database\Factories\FormTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * نموذج إلكتروني يحوّل نموذجاً ورقياً (requirements/05). حقوله في إصداراته: تعديلها ينشئ إصداراً
 * جديداً، والمعبأ قبله يبقى على إصداره (SaveFormTemplate).
 */
#[Fillable(['title', 'description', 'is_active'])]
class FormTemplate extends Model
{
    /** @use HasFactory<FormTemplateFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FormTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FormTemplateVersion::class)->orderBy('version');
    }

    /**
     * @return BelongsTo<FormTemplateVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FormTemplateVersion::class, 'current_version_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('documents')->logOnly(['title', 'description', 'is_active', 'current_version_id'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
