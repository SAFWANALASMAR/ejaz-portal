<?php

namespace App\Models;

use App\Models\Concerns\FlushesContentCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * صورة أو أيقونة في مكتبة الوسائط: الأصل في القرص الخاص باسم عشوائي، والنسخة المحسّنة للويب
 * (WebP) في القرص العام. والأيقونات SVG تُحفظ بعد تنظيفها أصلاً وحده، فلا تُنشر على رابط عام
 * (docs/security.md). تُضاف بأمر الاستيراد (AddMediaAsset)، ويعدّل المحرر عنوانها ونصها البديل.
 *
 * @property string $slug
 * @property string $title
 * @property string $original_name
 * @property string $original_path
 * @property string $mime_type
 * @property int $size
 * @property ?int $width
 * @property ?int $height
 * @property ?string $web_path
 * @property ?int $web_size
 * @property ?string $alt
 * @property bool $is_decorative
 * @property bool $from_template
 * @property ?string $legacy_url
 */
#[Fillable(['slug', 'title', 'original_name', 'original_path', 'mime_type', 'size', 'width', 'height', 'web_path', 'web_size', 'alt', 'is_decorative', 'from_template', 'legacy_url'])]
class MediaAsset extends Model
{
    use FlushesContentCache, LogsActivity;

    public const ORIGINALS_DIRECTORY = 'media/originals';

    public const WEB_DIRECTORY = 'media';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'web_size' => 'integer',
            'is_decorative' => 'boolean',
            'from_template' => 'boolean',
        ];
    }

    /**
     * قرص الأصول: الخاص من الإعدادات (filesystems.private_disk).
     */
    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    /**
     * النص البديل عند العرض: فارغ للزخرفية، وإلا ما كتبه المحرر.
     */
    public function altText(): string
    {
        return $this->is_decorative ? '' : (string) $this->alt;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title', 'alt', 'is_decorative'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
