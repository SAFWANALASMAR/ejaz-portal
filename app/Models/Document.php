<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\Publishable;
use App\Rules\SafeUrl;
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
 * الخاص، ولا يُقدَّم إلا منشوراً (GovernanceController). أو رابط مباشر https بدل الملف،
 * أحدهما وحده (SaveDocument وقيد القاعدة).
 */
#[Fillable(['slug', 'title', 'category', 'issued_at', 'version', 'file', 'url', 'file_size', 'is_view_only', 'description', 'is_published'])]
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
            'is_view_only' => 'boolean',
        ];
    }

    /**
     * وثيقة «للاطلاع فقط» (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٢٢): ملفها يُعرض في قارئ الصفحة وحده، بلا تنزيل ولا فتح
     * للملف نفسه في عارض المتصفح (GovernanceController)، كقارئ كتب المكتبة. والخيار لوثيقة بملف وحدها، فوثيقة الرابط المباشر
     * تبقى برابطها. وغير المحدد (سجل قبل الترحيل لم يُقرأ بعد) للاطلاع فقط.
     */
    public function isViewOnly(): bool
    {
        return filled($this->file) && $this->is_view_only !== false;
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

    /**
     * وثيقة برابط مباشر لا بملف: لا عرض مدمج ولا تنزيل. والرابط يُفحص عند العرض أيضاً، فسجل
     * كُتب في القاعدة دون SaveDocument لا يظهر رابطه.
     */
    public function isLink(): bool
    {
        return blank($this->file) && SafeUrl::isSafe($this->url, allowInternal: false);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
