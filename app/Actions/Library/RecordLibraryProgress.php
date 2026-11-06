<?php

namespace App\Actions\Library;

use App\Models\LibraryItem;
use App\Models\LibraryProgress;
use App\Models\User;
use App\Support\LibraryPageCount;
use App\Support\LibraryProgramProgress;
use App\Support\PageSet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * يسجّل بلاغ تقدّم صاحب الحساب في عنصر من المكتبة («قراءاتي» — docs/decisions/2026-11-01-my-reading.md). بلاغات الحساب الواحد
 * تُعالج واحداً بعد واحد (قفل لكل حساب)، وحدودها بساعتين للحساب كله لا لكل عنصر، فلا تُضخَّم بالتوازي بين عناصر كثيرة:
 * - الثواني: كل ثانية محتسبة تقابل ثانية من الساعة لم تُحتسب قبلها (ساعة الاستماع)، مع فسحة خمس ثوانٍ لا تتراكم.
 * - الصفحات الجديدة: صفحة لكل ثانيتين من ساعة الصفحات، ورصيد أقصاه خمسون صفحة بعد انقطاع، وثلاث لحساب لم يبلّغ قبل.
 *   والصفحات اتحاد بلا تكرار، وما لم يُقبل يعود إلى القارئ فيرسله لاحقاً.
 * - الإتمام للكتاب وحده: كل صفحاته بعددها من ملفه في الخادم. ولا تُقبل مدة المقطع من المتصفح أساساً لإتمام (فرع برامج المكتبة).
 * ولا سجل عمليات: بلاغ متكرر بلا قيمة تدقيقية، والصف نفسه بيانات صاحبه.
 */
class RecordLibraryProgress
{
    public const SECONDS_PER_PAGE = 2;

    public const FIRST_REPORT_PAGES = 3;

    public const MAX_PAGES_PER_REPORT = 50;

    public const MAX_SECONDS_PER_REPORT = 120;

    /**
     * فسحة بين ساعة المتصفح والخادم وزمن الطلب، لا تتراكم بين البلاغات.
     */
    public const SECONDS_SLACK = 5;

    /**
     * أطول تأخر يُحتمل لبلاغ بين إرساله ومعالجته (طلب سابق عالق، أو اتصال بطيء، أو انتظار القفل): ثواني البلاغ انتهت عند إرساله لا
     * عند وصوله، فتوضع في ساعة الاستماع من بعد آخر ما احتُسب ولو قبل «الآن - الثواني» بهذا القدر. ولا يرفع سقف الحساب كله: كل ثانية
     * محتسبة ما زالت تقابل ثانية من ساعة الخادم لم تُحتسب قبلها (قبول ملاحظات 4 نوفمبر، 5 نوفمبر 2026).
     */
    public const MAX_REPORT_DELAY = 60;

    /**
     * أطول مقطع يُقبل موضعه ومدته: ست ساعات.
     */
    public const MAX_DURATION = 21_600;

    /**
     * مدة بقاء ساعتي الحساب في الذاكرة المؤقتة بعد آخر بلاغ.
     */
    private const CLOCK_TTL = 86_400;

    /**
     * @param  array{pages?: list<int>, page?: int, seconds?: int, position?: int, duration?: int}  $data
     * @return array{progress: LibraryProgress|null, pages: list<int>} التقدّم (ولا شيء لمن أوقف الحفظ)، والصفحات المقبولة من البلاغ (ومنها ما سبق قبوله)
     *
     * @throws AuthorizationException
     * @throws LockTimeoutException
     */
    public function handle(User $user, LibraryItem $item, array $data): array
    {
        Gate::forUser($user)->authorize('create', [LibraryProgress::class, $item]);

        // «لا تحفظ قراءاتي» (قرار صفوان، 2 نوفمبر 2026): البلاغ يُقبل بصمت ولا يُكتب شيء، وصفحاته «مقبولة» فلا يعيدها القارئ
        if ($user->reading_tracking_disabled_at !== null) {
            return ['progress' => null, 'pages' => array_values(array_unique($data['pages'] ?? []))];
        }

        // وكتاب برنامج «قراءة كتاب» تُحصى صفحاته لمسجّله ولو كان قابلاً للتنزيل (docs/decisions/2026-11-02-reading-program.md)
        $tracksPages = $item->tracksPages() || LibraryProgramProgress::readsInProgram($user, $item);
        $total = $tracksPages ? LibraryPageCount::for($item) : null;

        return Cache::lock('library-progress:'.$user->id, 10)->block(5, fn (): array => DB::transaction(function () use ($user, $item, $data, $total, $tracksPages): array {
            // الحساب ما زال نشطاً غير مخفى، بقفل مشترك: لا يُكتب صف بعد حذف الحساب (AnonymizeUser يحدّث الصف نفسه)
            if (! User::query()->whereKey($user->id)->where('is_active', true)->whereNull('anonymized_at')->sharedLock()->exists()) {
                throw new AuthorizationException;
            }

            $now = now();
            DB::table('library_progress')->insertOrIgnore([
                'user_id' => $user->id,
                'library_item_id' => $item->id,
                'last_reported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /** @var LibraryProgress $progress */
            $progress = LibraryProgress::query()->whereBelongsTo($user)->where('library_item_id', $item->id)->lockForUpdate()->firstOrFail();
            $accepted = [];

            if ($tracksPages) {
                $accepted = $this->recordPages($user, $progress, $data, $total);
            }

            if ($item->tracksPlayback()) {
                $this->recordPlayback($user, $progress, $data);
            }

            $progress->forceFill(['last_reported_at' => $now])->save();

            // وتقدّم برامج المكتبة المسجل فيها بعد التسجيل وحده: أول فتح، وصفحات الكتاب المقبولة (مراجعة المشرف على #126، م٢-أ)
            LibraryProgramProgress::record($user, $item, $accepted, $total);

            return ['progress' => $progress, 'pages' => $accepted];
        }));
    }

    /**
     * @param  array{pages?: list<int>, page?: int}  $data
     * @return list<int>
     */
    private function recordPages(User $user, LibraryProgress $progress, array $data, ?int $total): array
    {
        $max = $total ?? PageSet::MAX_PAGE;
        $now = (float) now()->getTimestamp();
        $key = 'library-progress:'.$user->id.':pages';
        // بداية الرصيد: ساعة الصفحات، ولا يتراكم بعد انقطاع أكثر من خمسين صفحة. وحساب بلا ساعة يبدأ بثلاث
        $clock = (float) Cache::get($key, $now - self::FIRST_REPORT_PAGES * self::SECONDS_PER_PAGE);
        $clock = max($clock, $now - self::MAX_PAGES_PER_REPORT * self::SECONDS_PER_PAGE);
        $allowance = max(0, (int) floor(($now - $clock) / self::SECONDS_PER_PAGE));

        $reported = array_values(array_unique(array_filter($data['pages'] ?? [], fn (int $page): bool => $page >= 1 && $page <= $max)));
        // المحفوظ قبل معرفة العدد (أو لملف استُبدل، أو لكتاب استُعيد) يُقصّ إلى العدد الحقيقي قبل العدّ والإتمام (م٢ ون٤)
        $seen = $total === null ? $progress->pages() : PageSet::clip($progress->pages(), $total);
        $new = array_values(array_diff($reported, $seen));
        sort($new);
        $taken = array_slice($new, 0, $allowance);
        $seen = [...$seen, ...$taken];
        $count = count(array_unique($seen));

        // الساعة تتقدم بعد حفظ التقدّم وحده، فلا يُستهلك رصيد بلاغ تراجعت معاملته
        $advanced = $clock + count($taken) * self::SECONDS_PER_PAGE;
        DB::afterCommit(fn () => Cache::put($key, $advanced, self::CLOCK_TTL));

        $progress->forceFill([
            'pages_seen' => PageSet::encode($seen),
            'pages_count' => $count,
            'total_pages' => $total,
            'last_page' => isset($data['page']) ? min($data['page'], $max) : ($progress->last_page === null ? null : min($progress->last_page, $max)),
            // والإتمام بالعدد الحقيقي وحده: يُلغى إتمام لم يعد يبلغ العدد بعد القص
            'completed_at' => match (true) {
                $total === null => $progress->completed_at,
                $count >= $total => $progress->completed_at ?? now(),
                default => null,
            },
        ]);

        return array_values(array_intersect($reported, $seen));
    }

    /**
     * البلاغ يدّعي ثوانيَ انتهت عند إرساله، وقد يصل متأخراً حتى MAX_REPORT_DELAY. فتوضع من بعد آخر ما احتُسب (ساعة الاستماع)، ولا
     * تبدأ قبل [الآن - الثواني - التأخر]، ولا يُحتسب منها ما يتجاوز الآن بأكثر من الفسحة. وتنتهي الساعة عند آخر ثانية محتسبة لا عند
     * «الآن»، فبلاغ عالق في الشبكة لا يأكل من ثواني البلاغ الذي بعده (كان كذلك فضاع على رابط العرض 6 إلى 11% من الثواني — قبول
     * ملاحظات 4 نوفمبر). ولا يزيد المحتسب للحساب كله على الوقت الفعلي منذ بدء الساعة مع فسحة واحدة.
     *
     * @param  array{seconds?: int, position?: int, duration?: int}  $data
     */
    private function recordPlayback(User $user, LibraryProgress $progress, array $data): void
    {
        $now = (float) now()->getTimestamp();
        $key = 'library-progress:'.$user->id.':seconds';
        $seconds = min(max(0, $data['seconds'] ?? 0), self::MAX_SECONDS_PER_REPORT);
        // حساب بلا ساعة تبدأ ساعته الآن (البلاغ الأول عند بدء التشغيل)، فلا يُحتسب له قبلها إلا الفسحة
        $base = (float) Cache::get($key, $now);
        $start = max($base, $now - $seconds - self::MAX_REPORT_DELAY);
        $credit = (int) max(0, min($seconds, floor($now + self::SECONDS_SLACK - $start)));
        $advanced = max($base, $start + $credit);
        DB::afterCommit(fn () => Cache::put($key, $advanced, self::CLOCK_TTL));

        // المدة الأكبر المبلَّغ عنها، للعرض وحده
        $duration = max($progress->duration_seconds ?? 0, $data['duration'] ?? 0) ?: null;

        $progress->forceFill([
            'seconds_consumed' => $progress->seconds_consumed + $credit,
            'duration_seconds' => $duration,
            'last_position' => isset($data['position']) ? min($data['position'], $duration ?? self::MAX_DURATION) : $progress->last_position,
        ]);
    }
}
