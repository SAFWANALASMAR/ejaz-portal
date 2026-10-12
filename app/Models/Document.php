<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\Publishable;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * وثيقة حوكمة: لائحة أو سياسة أو تقرير أو وثيقة رسمية أو آلية عمل. ملفها PDF في القرص
 * الخاص، ولا يُقدَّم إلا منشوراً (GovernanceController).
 */
#[Fillable(['slug', 'title', 'category', 'issued_at', 'version', 'file', 'file_size', 'description', 'is_published'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use FlushesContentCache, HasFactory, LogsActivity, Publishable, SoftDeletes;

    public const DIRECTORY = 'documents';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'issued_at' => 'date',
            'file_size' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * الأحدث إصداراً أولاً، وما لا تاريخ له في آخرها. والمعرّف يثبّت الترتيب بين الصفحات.
     *
     * @param  Builder<Document>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByRaw('issued_at desc nulls last')->orderBy('title')->orderBy('id');
    }

    /**
     * قرص ملفات الوثائق: الخاص من الإعدادات (filesystems.private_disk).
     */
    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    /**
     * اسم الملف عند التنزيل: المعرّف بحروفه اللاتينية، فلا مشكلة ترميز في المتصفحات.
     */
    public function downloadName(): string
    {
        return $this->slug.'.pdf';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
