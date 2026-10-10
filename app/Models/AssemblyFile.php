<?php

namespace App\Models;

use App\Enums\AssemblyFileAudience;
use App\Enums\AssemblyFileCategory;
use App\Support\AssemblyArea;
use Database\Factories\AssemblyFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * ملف في مكتبة ملفات الأعضاء (القوائم المالية، والتقارير، والقرارات)، لكل من يصل إلى منطقة الجمعية
 * العمومية أو لمجلس الإدارة وحده. PDF في القرص الخاص، ويُطلب بمعرّفه uuid.
 *
 * @property AssemblyFileCategory $category
 * @property AssemblyFileAudience $audience
 */
#[Fillable(['title', 'category', 'audience', 'file'])]
class AssemblyFile extends Model
{
    /** @use HasFactory<AssemblyFileFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public const DIRECTORY = 'assembly-files';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => AssemblyFileCategory::class,
            'audience' => AssemblyFileAudience::class,
            'file_size' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    /**
     * رابط التنزيل في منطقة الأعضاء: مؤقت موقّع (AssemblyArea::LINK_MINUTES).
     */
    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute('assembly.files.download', now()->addMinutes(AssemblyArea::LINK_MINUTES), [$this]);
    }

    /**
     * اسم الملف عند التنزيل من عنوانه، لا اسمه العشوائي في القرص.
     */
    public function downloadName(): string
    {
        return (Str::slug($this->title, '-', 'ar') ?: 'file').'.pdf';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('assembly')->logOnly(['title', 'category', 'audience', 'file'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
