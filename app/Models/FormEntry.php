<?php

namespace App\Models;

use App\Enums\FormEntryStatus;
use Database\Factories\FormEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * نموذج معبأ على إصدار من نموذج إلكتروني: إجاباته، ومرفقاته PDF، وتواقيعه صور PNG، في القرص الخاص.
 * يُعدَّل مسودةً، ثم يُعتمد فيُقفل (SaveFormEntry وApproveFormEntry).
 *
 * @property FormEntryStatus $status
 * @property array<string, mixed>|null $answers
 * @property list<array{key: string, path: string, name: string, size: int}>|null $files
 * @property list<array{key: string, path: string}>|null $signatures
 * @property Carbon|null $approved_at
 */
class FormEntry extends Model
{
    /** @use HasFactory<FormEntryFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public const FILES_DIRECTORY = 'form-files';

    public const SIGNATURES_DIRECTORY = 'form-signatures';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FormEntryStatus::class,
            'answers' => 'array',
            'files' => 'array',
            'signatures' => 'array',
            'approved_at' => 'datetime',
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

    protected static function booted(): void
    {
        static::creating(function (FormEntry $entry): void {
            $entry->reference ??= self::newReference();
        });
    }

    /**
     * رقم مرجعي عشوائي من عشرة حروف وأرقام.
     */
    public static function newReference(): string
    {
        do {
            $reference = Str::upper(Str::random(10));
        } while (self::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    /**
     * @return BelongsTo<FormTemplateVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FormTemplateVersion::class, 'form_template_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        // الإجابات والملفات لا تدخل السجل: قد تحمل بيانات شخصية، وتُرى في المعبأ نفسه
        return LogOptions::defaults()->useLogName('documents')->logOnly(['status', 'approved_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
