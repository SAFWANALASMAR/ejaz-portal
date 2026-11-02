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
 * تقدّم المشارك في برنامج مكتبة من سجل «قراءاتي» (library_progress)، والإتمام بقواعد الخادم وحده
 * (docs/decisions/2026-11-02-reading-program.md):
 *
 * - **قراءة كتاب:** المرور على كل صفحاته بعددها من ملفه في الخادم (completed_at في سجل التقدّم).
 * - **استماع أو مشاهدة من ملف أو رابط مباشر:** ثواني التشغيل الفعلي بساعة الحساب (seconds_consumed) تبلغ مدة العنصر من الخادم،
 *   بهامش 5% لما يفوت عند بدء التشغيل وإيقافه.
 * - **مقطع يوتيوب:** لا يُعرف ما شوهد منه (سياسة المحتوى تمنع واجهة يوتيوب)، فالإتمام بعد مرور مدته من أول فتح له.
 */
final class LibraryProgramProgress
{
    /**
     * نسبة ما يلزم من ثواني التشغيل إلى مدة العنصر.
     */
    public const MEDIA_THRESHOLD = 0.95;

    /**
     * @param  array{percent: int, complete: bool, started: bool, pages: int, seconds: int}  $state
     */
    private function __construct(public readonly array $state) {}

    /**
     * $registration: تسجيل المستخدم، فيُحسب وقت يوتيوب من الأحدث بين أول فتح والتسجيل، لا من فتح قبل التسجيل. و$progress المحمّل
     * مسبقاً لقائمة (false: لا تقدّم)، فلا يُسأل لكل صف.
     */
    public static function for(Program $program, User $user, ?ProgramRegistration $registration = null, LibraryProgress|false|null $progress = null): self
    {
        $kind = $program->libraryKind();
        $item = $program->libraryItem;
        $progress = $progress === false ? null : ($progress ?? ($item === null ? null : LibraryProgress::query()->where('user_id', $user->id)->where('library_item_id', $item->id)->first()));

        if ($kind === null || $item === null || $progress === null) {
            return new self(['percent' => 0, 'complete' => false, 'started' => false, 'pages' => 0, 'seconds' => 0]);
        }

        [$done, $total] = self::measure($kind, $item, $progress, $registration);
        // والكتاب بعدد صفحاته الحالي، فملف استُبدل بأطول لا يبقى إتمامه القديم (المراجعة الأمنية، ن٢)
        $complete = $kind === LibraryProgramKind::Reading
            ? $progress->completed_at !== null && $total > 0 && $done >= $total
            : $total > 0 && $done >= (int) floor($total * self::MEDIA_THRESHOLD);

        return new self([
            'percent' => $complete ? 100 : ($total > 0 ? min(99, (int) floor($done * 100 / $total)) : 0),
            'complete' => $complete,
            'started' => true,
            'pages' => $progress->pages_count,
            'seconds' => $progress->seconds_consumed,
        ]);
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
     * ما أُنجز وما يلزم: الصفحات للكتاب، والثواني للصوت والمرئي.
     *
     * @return array{0: int, 1: int}
     */
    private static function measure(LibraryProgramKind $kind, LibraryItem $item, LibraryProgress $progress, ?ProgramRegistration $registration): array
    {
        if ($kind === LibraryProgramKind::Reading) {
            return [$progress->pages_count, (int) ($item->page_count ?? 0)];
        }

        $duration = (int) ($item->duration_seconds ?? 0);

        // يوتيوب: الوقت منذ أول فتح بعد التسجيل، لا يتجاوز المدة (المراجعتان: فتح قديم قبل التسجيل لا يُتمّ فوراً)
        if ($item->youtubeId() !== null) {
            $since = $progress->created_at;

            if ($registration?->created_at !== null && ($since === null || $registration->created_at->gt($since))) {
                $since = $registration->created_at;
            }

            return [min($duration, (int) max(0, $since?->diffInSeconds(now()) ?? 0)), $duration];
        }

        return [$progress->seconds_consumed, $duration];
    }
}
