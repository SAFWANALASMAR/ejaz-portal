<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بند في معرض الشواهد بترتيبه: ألبوم من مكتبة الصور أو عنصر من الأرشيف، ومعه ما اختير منه (paths، وnull كله).
 * إشارة لا نسخة: صورة حُذفت من ألبومها أو ملف من عنصره، أو عنصر حُذف، يغيب من المعرض وحده.
 *
 * @property ?list<string> $paths
 */
#[Fillable(['position', 'album_id', 'archive_item_id', 'paths'])]
class EvidenceGalleryItem extends Model
{
    public const IMAGE_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'paths' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EvidenceGallery, $this>
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(EvidenceGallery::class, 'evidence_gallery_id');
    }

    /**
     * @return BelongsTo<Album, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * والمحذوف ناعماً من الأرشيف لا يظهر (النطاق الافتراضي).
     *
     * @return BelongsTo<ArchiveItem, $this>
     */
    public function archiveItem(): BelongsTo
    {
        return $this->belongsTo(ArchiveItem::class);
    }

    /**
     * صور الألبوم المختارة بترتيبها في الألبوم (القرص العام).
     *
     * @return list<string>
     */
    public function albumPhotos(): array
    {
        $photos = $this->album?->photoList() ?? [];

        return $this->paths === null ? $photos : array_values(array_intersect($photos, $this->paths));
    }

    /**
     * ملفات عنصر الأرشيف المختارة بترتيبها في العنصر (القرص الخاص).
     *
     * @return list<array{path: string, name: string, size: int}>
     */
    public function archiveFiles(): array
    {
        $files = $this->archiveItem->files ?? [];

        return $this->paths === null ? $files : array_values(array_filter($files, fn (array $file): bool => in_array($file['path'], $this->paths, true)));
    }

    /**
     * يُعرض البند في المعرض: صور الألبوم دائماً، وعنصر الأرشيف ما دام موجوداً ومُصدر الرابط يطّلع عليه الآن (فتضييق
     * تصنيفه أو نقل العنصر إلى تصنيف لا يراه يُخفيه من الرابط فوراً). والعلاقات محمّلة قبلها.
     */
    public function visibleTo(EvidenceGallery $gallery): bool
    {
        if ($this->album_id !== null) {
            return true;
        }

        return $this->archiveItem !== null && $gallery->issuer !== null && $gallery->issuer->can('view', $this->archiveItem);
    }

    /**
     * معرّف الملف في رابطه الموقّع، بدل مساره في القرص.
     */
    public static function fileKey(string $path): string
    {
        return substr(hash('sha256', $path), 0, 24);
    }

    public static function imageMime(string $path): ?string
    {
        return self::IMAGE_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
    }
}
