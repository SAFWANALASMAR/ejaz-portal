<?php

namespace App\Models;

use App\Actions\Settings\UpdateSiteSettings;
use App\Enums\PageRevisionStatus;
use App\Models\Contracts\HasPublicImages;
use App\Support\ContentBlocks;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * نسخة من صفحة ثابتة في «محرر الصفحات» (EditablePages): المحتوى كاملاً (العنوان، والوصف، وصورة الرأس، والمقاطع بإظهارها
 * وترتيبها)، ومن كتبها ومن نشرها ومتى. وتُكتب بإجراءاتها وحدها (SavePageDraft وPublishPageDraft وRevertPageRevision)، وكل
 * إجراء يُسجَّل في سجل العمليات. وصورها لا تُحذف باستبدالها، لأن الرجوع إلى نسخة سابقة يعيدها (ejaz:prune-public-files يعدّها
 * مرتبطة ما بقيت نسختها).
 *
 * @property int $id
 * @property int $page_id
 * @property PageRevisionStatus $status
 * @property array<string, mixed>|null $content
 * @property int|null $author_id
 * @property int|null $published_by
 * @property Carbon|null $published_at
 * @property int|null $reverted_from_id
 * @property-read Page $page
 */
#[Fillable(['page_id', 'status', 'content', 'author_id', 'published_by', 'published_at', 'reverted_from_id'])]
class PageRevision extends Model implements HasPublicImages
{
    /**
     * مدة رابط المعاينة الموقّع.
     */
    public const PREVIEW_MINUTES = 30;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageRevisionStatus::class,
            'content' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeStatus(Builder $query, PageRevisionStatus $status): void
    {
        $query->where('status', $status->value);
    }

    /**
     * رابط المعاينة: موقّع وقصير المدة، ولا يفتحه إلا من له pages.edit_all بعد الدخول (PagePreviewController).
     */
    public function previewUrl(): string
    {
        return URL::temporarySignedRoute('pages.preview', now()->addMinutes(self::PREVIEW_MINUTES), ['revision' => $this->id]);
    }

    public function imageDirectory(): string
    {
        return Page::IMAGE_DIRECTORY;
    }

    /**
     * صور المقاطع في مجلد الصفحات، وصورة الرأس في مجلد الإعدادات مع خانات «محتوى الرئيسية».
     *
     * @return array<string, list<string>>
     */
    public function publicImages(): array
    {
        $content = is_array($this->content) ? $this->content : [];
        $image = $content['image'] ?? null;

        return [
            'blocks' => ContentBlocks::images($content['blocks'] ?? []),
            'image' => is_string($image) && $image !== '' ? [$image] : [],
        ];
    }

    /**
     * مجلد صورة الرأس، وهو مجلد خانات «محتوى الرئيسية» (UpdateHomeContent).
     */
    public static function heroImageDirectory(): string
    {
        return UpdateSiteSettings::IMAGE_DIRECTORY;
    }
}
