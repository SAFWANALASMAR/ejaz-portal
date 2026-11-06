<?php

namespace App\Actions\Accounts;

use App\Enums\MeetingBody;
use App\Enums\MeetingStatus;
use App\Enums\RoleName;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Models\Meeting;
use App\Models\User;
use App\Support\AccountSettings;
use App\Support\PortalStats;

/**
 * ما يزيد في رئيسية حساب المشرف العام (لوحته — ملاحظات المدير التنفيذي عبر صفوان، 25 أكتوبر 2026):
 * - إحصائيات عامة للاطلاع: البرامج، والتسجيل، والجمعية العمومية، والاجتماعات، والمشاركات. من المحسوب في الخلفية
 *   (PortalStats)، فلا استعلام عدّ في كل زيارة، ولا تظهر قبل أول حساب.
 * - اجتماع تيمز القادم أو الجاري: للجمعية العمومية لمن يحضره (MeetingPolicy::attend)، ولمجلس الإدارة واللجان لمن يراها
 *   (Meeting::visibleTo، والمشرف العام اطلاعاً — مراجعة المشرف، 26 أكتوبر 2026). والزر مؤجل لغير الرقباء حتى تُظهر
 *   البوابات المؤجلة من اللوحة (AccountSettings).
 */
class BuildOversight
{
    /**
     * الاجتماع الجاري يبقى زره حتى ثلاث ساعات بعد بدئه.
     */
    public const RUNNING_HOURS = 3;

    /**
     * @return array{stats: array<string, int>|null, meeting: Meeting|null}
     */
    public function handle(User $user): array
    {
        $overseer = RoleName::isOverseer($user);
        // رابط الاجتماع سرّ، و«حسابي» بلا التحقق الثنائي: لا يُعرض لمن يلزمه ولم يفعّله (المراجعة الأمنية على #197)
        $secured = EnsureTwoFactorIsEnabled::satisfied($user);

        return [
            'stats' => $overseer ? self::stats() : null,
            'meeting' => $secured && ($overseer || AccountSettings::showsDeferredPortals()) ? self::nextMeeting($user) : null,
        ];
    }

    /**
     * @return array<string, int>|null
     */
    private static function stats(): ?array
    {
        $stats = PortalStats::cached();

        if ($stats === null) {
            return null;
        }

        return [
            'programs' => $stats['programs'] ?? 0,
            'registrations' => $stats['registrations'] ?? 0,
            'members' => $stats['members'] ?? 0,
            'meetings' => ($stats['upcoming_meetings'] ?? 0) + ($stats['held_meetings'] ?? 0),
            'submissions' => $stats['submissions'] ?? 0,
        ];
    }

    public static function nextMeeting(User $user): ?Meeting
    {
        $upcoming = fn () => Meeting::query()->with('committee')
            ->where('status', MeetingStatus::Scheduled->value)
            ->whereNotNull('online_join_url')
            ->where('starts_at', '>', now()->subHours(self::RUNNING_HOURS))
            ->orderBy('starts_at');

        $assembly = $upcoming()->generalAssembly()->first();
        // ومن لا يرى الاجتماعات لا يُسأل له (visibleTo تجعل الاستعلام «1 = 0»)
        $council = $user->can('meetings.view') ? $upcoming()->where('body', '!=', MeetingBody::GeneralAssembly->value)->visibleTo($user)->first() : null;

        return collect([$assembly !== null && $user->can('attend', $assembly) ? $assembly : null, $council])
            ->filter()
            ->sortBy(fn (Meeting $meeting) => $meeting->starts_at)
            ->first();
    }
}
