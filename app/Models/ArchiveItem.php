<?php

namespace App\Models;

use App\Enums\ArchiveItemType;
use Database\Factories\ArchiveItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * عنصر في الأرشيف الشامل: وثيقة، أو مراسلة واردة أو صادرة برقم قيد تسلسلي في سنتها، أو شاهد على تنفيذ
 * برنامج (requirements/05). وملفاته PDF وصور في القرص الخاص.
 *
 * @property ArchiveItemType $type
 * @property Carbon $dated_on
 * @property list<array{path: string, name: string, size: int}>|null $files
 */
#[Fillable(['type', 'archive_category_id', 'title', 'entity', 'dated_on', 'program_id', 'description'])]
class ArchiveItem extends Model
{
    /** @use HasFactory<ArchiveItemFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public const DIRECTORY = 'archive';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ArchiveItemType::class,
            'dated_on' => 'date',
            'year' => 'integer',
            'registration_number' => 'integer',
            'files' => 'array',
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
     * @return BelongsTo<ArchiveCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ArchiveCategory::class, 'archive_category_id');
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    /**
     * رقم القيد كما يُكتب: السنة/الرقم بأربع خانات، أو null لغير المراسلات.
     */
    public function registration(): ?string
    {
        return $this->registration_number === null ? null : $this->year.'/'.str_pad((string) $this->registration_number, 4, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('documents')
            ->logOnly(['type', 'archive_category_id', 'title', 'entity', 'dated_on', 'program_id', 'registration_number'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
