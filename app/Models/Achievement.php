<?php

namespace App\Models;

use App\Enums\AchievementStatus;
use App\Models\Concerns\FlushesContentCache;
use Database\Factories\AchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * إنجاز للجمعية (docs/decisions/2026-11-02-achievements.md): عنوان وتاريخ وتفاصيل، ومرفق اختياري (صورة يُعاد ترميزها أو PDF
 * حقيقي) في القرص الخاص باسم عشوائي، يُقدَّم برابط مؤقت موقّع بعد فحص AchievementPolicy::viewAttachment.
 *
 * @property AchievementStatus $status
 * @property Carbon $achieved_on
 */
#[Fillable(['title', 'achieved_on', 'details', 'status'])]
class Achievement extends Model
{
    /** @use HasFactory<AchievementFactory> */
    // ونشره يمسح ذاكرة المحتوى، فتعود «إنجازاتنا» إلى خريطة الموقع والفهرسة بأول إنجاز (feat/seo-foundation)
    use FlushesContentCache, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    public const DIRECTORY = 'achievements';

    /**
     * مدة رابط المرفق الموقّع بالدقائق: تكفي من فتح الصفحة ثم ضغط الرابط بعد قراءتها. والنشر يُفحص عند الطلب أيضاً.
     */
    public const LINK_MINUTES = 60;

    /**
     * نوع المرفق من امتداد اسمه المحفوظ، وامتداده من نوع الملف المفحوص (PrivateUpload::check)، لا من اسمه المرسل.
     */
    public const MIME_TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AchievementStatus::class,
            'achieved_on' => 'date',
            'attachment_size' => 'integer',
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
     * المنشور وحده.
     *
     * @param  Builder<Achievement>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', AchievementStatus::Published->value);
    }

    /**
     * الخط الزمني: المنشور بالأحدث تاريخاً.
     *
     * @param  Builder<Achievement>  $query
     */
    public function scopeTimeline(Builder $query): void
    {
        $query->published()->orderByDesc('achieved_on')->orderByDesc('id');
    }

    /**
     * عدد الإنجازات المنشورة هذا العام بتوقيت الرياض، بتاريخ الإنجاز نفسه.
     */
    public static function publishedThisYear(): int
    {
        $year = now(config('app.display_timezone'));

        return self::query()->published()
            ->whereBetween('achieved_on', [$year->copy()->startOfYear()->toDateString(), $year->copy()->endOfYear()->toDateString()])
            ->count();
    }

    public function isPublished(): bool
    {
        return $this->status === AchievementStatus::Published;
    }

    public function attachmentExtension(): ?string
    {
        $extension = $this->attachment_name !== null ? strtolower(pathinfo($this->attachment_name, PATHINFO_EXTENSION)) : null;

        return isset(self::MIME_TYPES[$extension]) ? $extension : null;
    }

    public function attachmentIsImage(): bool
    {
        return in_array($this->attachmentExtension(), ['jpg', 'png', 'webp'], true);
    }

    /**
     * رابط المرفق المؤقت الموقّع، أو لا رابط لإنجاز بلا مرفق.
     */
    public function attachmentUrl(): ?string
    {
        return $this->attachment !== null && $this->attachmentExtension() !== null
            ? URL::temporarySignedRoute('achievements.attachment', now()->addMinutes(self::LINK_MINUTES), [$this])
            : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('content')
            ->logOnly(['title', 'achieved_on', 'details', 'status', 'attachment', 'attachment_name', 'updated_by'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
