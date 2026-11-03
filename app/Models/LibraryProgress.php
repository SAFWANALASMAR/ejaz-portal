<?php

namespace App\Models;

use App\Enums\LibrarySection;
use App\Support\PageSet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تقدّم مستخدم في عنصر من المكتبة («قراءاتي» — docs/decisions/2026-11-01-my-reading.md). يكتبه RecordLibraryProgress وحده من
 * بلاغات القارئ والمشغّل، ولا يُعدَّل من نموذج. بيانات شخصية: لصاحبها وحده (LibraryProgressPolicy)، وللإدارة مجاميعها
 * (totals)، وتُحذف بحذف الحساب (AnonymizeUser).
 *
 * @property int $id
 * @property int $user_id
 * @property int $library_item_id
 * @property string|null $pages_seen
 * @property int $pages_count
 * @property int|null $last_page
 * @property int|null $total_pages
 * @property int $seconds_consumed
 * @property int|null $last_position
 * @property int|null $duration_seconds
 * @property Carbon|null $completed_at
 * @property Carbon $last_reported_at
 * @property-read LibraryItem $item
 */
class LibraryProgress extends Model
{
    protected $table = 'library_progress';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pages_count' => 'integer',
            'last_page' => 'integer',
            'total_pages' => 'integer',
            'seconds_consumed' => 'integer',
            'last_position' => 'integer',
            'duration_seconds' => 'integer',
            'completed_at' => 'datetime',
            'last_reported_at' => 'datetime',
        ];
    }

    /**
     * تقدّم صاحب الحساب في عناصر برامج المكتبة لقائمة تسجيلاته، باستعلام واحد (LibraryProgramProgress)، بمعرّف العنصر.
     *
     * @param  iterable<ProgramRegistration>  $registrations
     * @return array<int, LibraryProgress>
     */
    public static function forRegistrations(User $user, iterable $registrations): array
    {
        $items = collect($registrations)->map(fn (ProgramRegistration $registration): ?int => $registration->program->library_item_id)->filter()->unique()->values()->all();

        return $items === [] ? [] : self::query()->whereBelongsTo($user)->whereIn('library_item_id', $items)->get()->keyBy('library_item_id')->all();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<LibraryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class, 'library_item_id');
    }

    /**
     * تقدّم المستخدم في العناصر المنشورة وحدها، الأحدث أولاً. وما أُلغي نشره أو حُذف يبقى صفه ولا يظهر.
     *
     * @param  Builder<LibraryProgress>  $query
     */
    public function scopeVisibleFor(Builder $query, User $user): void
    {
        $query->whereBelongsTo($user)
            ->whereHas('item', fn (Builder $items) => $items->published())
            ->with('item')
            ->latest('last_reported_at')
            ->orderByDesc('id');
    }

    /**
     * @return list<int>
     */
    public function pages(): array
    {
        return PageSet::decode($this->pages_seen);
    }

    /**
     * نسبة التقدّم من 0 إلى 100، أو null حين لا يُعرف المقام (مقطع يوتيوب، أو كتاب لم يُعد عدد صفحاته).
     */
    public function percent(): ?int
    {
        if ($this->item->section->takesPdf()) {
            return $this->total_pages ? (int) min(100, round($this->pages_count * 100 / $this->total_pages)) : null;
        }

        return $this->duration_seconds && $this->last_position !== null
            ? (int) min(100, round($this->last_position * 100 / $this->duration_seconds))
            : null;
    }

    /**
     * مجاميع البوابة بلا بيانات شخصية: الصفحات المقروءة، ودقائق الاستماع والمشاهدة، وعدد القرّاء. لصفحة «البوابة بالأرقام»
     * ولوحة الإحصائيات (docs/decisions/2026-11-01-my-reading.md)، باستعلام واحد. ومع المستخدم: مجاميعه هو في «قراءاتي».
     *
     * @return array{pages_read: int, listened_minutes: int, watched_minutes: int, readers: int}
     */
    public static function totals(?User $user = null): array
    {
        $row = DB::table('library_progress')
            ->join('library_items', 'library_items.id', '=', 'library_progress.library_item_id')
            // العناصر المنشورة غير المحذوفة وحدها، كما في «قراءاتي» (visibleFor): ما أُلغي نشره أو حُذف لا يُعدّ
            ->where('library_items.is_published', true)
            ->whereNull('library_items.deleted_at')
            ->when($user, fn ($query, User $user) => $query->where('library_progress.user_id', $user->id))
            ->selectRaw('coalesce(sum(library_progress.pages_count), 0) as pages')
            ->selectRaw('coalesce(sum(case when library_items.section = ? then library_progress.seconds_consumed else 0 end), 0) as listened', [LibrarySection::Audio->value])
            ->selectRaw('coalesce(sum(case when library_items.section = ? then library_progress.seconds_consumed else 0 end), 0) as watched', [LibrarySection::Videos->value])
            ->selectRaw('count(distinct library_progress.user_id) as readers')
            ->first();

        return [
            'pages_read' => (int) ($row->pages ?? 0),
            'listened_minutes' => intdiv((int) ($row->listened ?? 0), 60),
            'watched_minutes' => intdiv((int) ($row->watched ?? 0), 60),
            'readers' => (int) ($row->readers ?? 0),
        ];
    }

    /**
     * الثواني بصيغة المشغّل: «4:05» أو «1:02:09».
     */
    public static function clock(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds % 60)
            : sprintf('%d:%02d', $minutes, $seconds % 60);
    }
}
