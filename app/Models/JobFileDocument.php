<?php

namespace App\Models;

use Database\Factories\JobFileDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * مستند في ملف وظيفة: PDF حقيقي أو صورة يُعاد ترميزها، في القرص الخاص، ويُنزَّل برابط مؤقت موقّع.
 */
#[Fillable(['title', 'description'])]
class JobFileDocument extends Model
{
    /** @use HasFactory<JobFileDocumentFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public const DIRECTORY = 'job-files';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
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
     * @return BelongsTo<JobFile, $this>
     */
    public function jobFile(): BelongsTo
    {
        return $this->belongsTo(JobFile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('job_files')
            ->logOnly(['job_file_id', 'title', 'description', 'original_name'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
