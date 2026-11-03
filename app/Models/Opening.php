<?php

namespace App\Models;

use App\Enums\OpeningKind;
use App\Models\Concerns\DefinesFormFields;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\Publishable;
use App\Models\Contracts\HasFormFields;
use Database\Factories\OpeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
class Opening extends Model implements HasFormFields
{
    /** @use HasFactory<OpeningFactory> */
    // خريطة الموقع في ذاكرة المحتوى تذكر الفرص المنشورة، فيمسحها حفظ الفرصة وحذفها كبقية المحتوى
    use DefinesFormFields, FlushesContentCache, HasFactory, LogsActivity, Publishable, SoftDeletes;

    /**
     * الحقول التي لا تُنشر وفيها علامة النص الناقص، وقيم المصفوفات منها بما في داخلها (Publishable).
     *
     * @var list<string>
     */
    protected array $missingTextFields = ['title', 'summary', 'description', 'form_fields'];

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
     * يُقبل التقديم ما دامت منشورة ولم يمض موعد إغلاقها.
     */
    public function acceptsApplications(): bool
    {
        return $this->is_published && ! $this->trashed() && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
