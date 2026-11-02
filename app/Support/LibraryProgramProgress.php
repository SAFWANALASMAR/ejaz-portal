<?php

namespace App\Support;

use App\Enums\LibraryProgramKind;
use App\Enums\RegistrationStatus;
use App\Models\LibraryItem;
use App\Models\LibraryProgress;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;

/**
 * تقدّم المشارك في برنامج مكتبة بعد تسجيله فيه وحده (docs/decisions/2026-11-02-reading-program.md، ومراجعة المشرف على #126 م٢)،
 * والإتمام بقواعد الخادم:
 *
 * - **قراءة كتاب:** صفحات الكتاب التي مر عليها بعد التسجيل (program_registrations.pages_seen، يكتبها record من بلاغات القارئ
 *   بحد صفحة لكل ثانيتين منذ بدايته)، تبلغ عددها من ملفه في الخادم. فقراءة سابقة للتسجيل لا تُتمّ البرنامج.
 * - **استماع أو مشاهدة من ملف أو رابط مباشر:** ثواني التشغيل الفعلي بساعة الحساب (seconds_consumed) بعد التسجيل (بطرح ما كان عند
 *   التسجيل) تبلغ 95% من مدة العنصر من الخادم.
 * - **مقطع يوتيوب:** لا يُعرف ما شوهد منه (سياسة المحتوى تمنع واجهة يوتيوب)، فالإتمام بعد مرور مدته من أول فتح بعد التسجيل.
 */
final class LibraryProgramProgress
{
    /**
     * نسبة ما يلزم من ثواني التشغيل إلى مدة العنصر.
     */
    public const MEDIA_THRESHOLD = 0.95;

    /**
     * @param  array{percent: int, complete: bool, started: bool}  $state
     */
    private function __construct(public readonly array $state) {}

    /**
     * $registration تسجيل المستخدم في البرنامج (وإلا يُقرأ). و$progress المحمّل مسبقاً لقائمة (false: لا تقدّم)، فلا يُسأل لكل صف.
     */
    public static function for(Program $program, User $user, ?ProgramRegistration $registration = null, LibraryProgress|false|null $progress = null): self
    {
        $kind = $program->libraryKind();
        $item = $program->libraryItem;
        $registration ??= ProgramRegistration::query()->where('program_id', $program->id)->where('user_id', $user->id)->first();

        if ($kind === null || $item === null || $registration === null || $registration->started_at === null) {
            return new self(['percent' => 0, 'complete' => false, 'started' => false]);
        }

        if ($kind !== LibraryProgramKind::Reading && $item->youtubeId() === null) {
            $progress = $progress === false ? null : ($progress ?? LibraryProgress::query()->where('user_id', $user->id)->where('library_item_id', $item->id)->first());
        }

        [$done, $total] = self::measure($kind, $item, $registration, $progress instanceof LibraryProgress ? $progress : null);
        $complete = $total > 0 && $done >= ($kind === LibraryProgramKind::Reading ? $total : (int) floor($total * self::MEDIA_THRESHOLD));

        return new self([
            'percent' => $complete ? 100 : ($total > 0 ? min(99, (int) floor($done * 100 / $total)) : 0),
            'complete' => $complete,
            'started' => $registration->opened_at !== null,
        ]);
    }

    /**
     * بلاغ من القارئ أو المشغّل لعنصر: يسجّل أول فتح بعد التسجيل، ويضيف صفحات الكتاب المقبولة لتسجيلات المستخدم الجارية في برامج
     * المكتبة على هذا العنصر. بحد صفحة لكل ثانيتين منذ بداية التسجيل وثلاث صفحات للبداية، فلا يُتمّ بلاغ مكتوب البرنامج دفعة.
     * يُستدعى داخل معاملة البلاغ (RecordLibraryProgress)، وبعد فحص «لا تحفظ قراءاتي».
     *
     * @param  list<int>  $pages
     */
    public static function record(User $user, LibraryItem $item, array $pages, ?int $total): void
    {
        $registrations = ProgramRegistration::query()
            ->where('user_id', $user->id)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->whereNull('completed_at')
            ->whereNotNull('started_at')
            ->whereHas('program', fn ($program) => $program->where('library_item_id', $item->id))
            ->lockForUpdate()
            ->get();

        foreach ($registrations as $registration) {
            $registration->opened_at ??= now();

            if ($pages !== [] && $item->section->takesPdf()) {
                $seen = PageSet::decode($registration->pages_seen);
                $budget = max(0, intdiv((int) $registration->started_at?->diffInSeconds(now()), 2) + 3 - count($seen));
                $new = array_slice(array_values(array_diff($pages, $seen)), 0, $budget);
                $merged = PageSet::decode(PageSet::encode([...$seen, ...$new]));
                $merged = $total !== null ? PageSet::clip($merged, $total) : $merged;

                $registration->pages_seen = PageSet::encode($merged);
                $registration->pages_count = count($merged);
            }

            $registration->saveQuietly();
        }
    }

    /**
     * المستخدم مسجل مؤكد في برنامج «قراءة كتاب» منشور لم ينتهِ، عنصره هذا الكتاب: فيُفتح له في قارئ الصفحة وتُحصى صفحاته ولو كان
     * الكتاب قابلاً للتنزيل في المكتبة، ولا يتغير خيار تنزيله.
     */
    public static function readsInProgram(?User $user, LibraryItem $item): bool
    {
        if ($user === null || ! $item->section->takesPdf() || blank($item->file)) {
            return false;
        }

        return Program::query()->published()->where('library_item_id', $item->id)
            ->whereHas('type', fn ($type) => $type->where('slug', LibraryProgramKind::Reading->value))
            ->where(fn ($open) => $open->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->whereHas('registrations', fn ($registrations) => $registrations->where('user_id', $user->id)->where('status', RegistrationStatus::Confirmed->value))
            ->exists();
    }

    public function percent(): int
    {
        return $this->state['percent'];
    }

    public function complete(): bool
    {
        return $this->state['complete'];
    }

    public function started(): bool
    {
        return $this->state['started'];
    }

    /**
     * ما أُنجز بعد التسجيل وما يلزم: الصفحات للكتاب، والثواني للصوت والمرئي.
     *
     * @return array{0: int, 1: int}
     */
    private static function measure(LibraryProgramKind $kind, LibraryItem $item, ProgramRegistration $registration, ?LibraryProgress $progress): array
    {
        if ($kind === LibraryProgramKind::Reading) {
            return [$registration->pages_count, (int) ($item->page_count ?? 0)];
        }

        $duration = (int) ($item->duration_seconds ?? 0);

        // يوتيوب: الوقت منذ أول فتح بعد التسجيل، لا يتجاوز المدة
        if ($item->youtubeId() !== null) {
            return [$registration->opened_at === null ? 0 : min($duration, (int) $registration->opened_at->diffInSeconds(now())), $duration];
        }

        return [max(0, (int) ($progress?->seconds_consumed ?? 0) - $registration->seconds_baseline), $duration];
    }
}
