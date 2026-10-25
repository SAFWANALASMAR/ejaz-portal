<?php

namespace App\Actions\Accounts;

use App\Enums\MeetingStatus;
use App\Enums\RoleName;
use App\Models\Meeting;
use App\Models\User;
use App\Support\AccountSettings;
use App\Support\PortalStats;

/**
 * ما يزيد في رئيسية حساب المشرف العام (لوحته — ملاحظات المدير التنفيذي عبر صفوان، 25 أكتوبر 2026):
 * - إحصائيات عامة للاطلاع: البرامج، والتسجيل، والجمعية العمومية، والاجتماعات، والمشاركات. من المحسوب في الخلفية
 *   (PortalStats)، فلا استعلام عدّ في كل زيارة، ولا تظهر قبل أول حساب.
 * - اجتماع تيمز القادم أو الجاري لمن يحضره (MeetingPolicy::attend)، والجمعية العمومية مؤجلة لغير المشرف العام والمسؤول
 *   التقني حتى تُظهر من اللوحة (AccountSettings).
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

        return [
            'stats' => $overseer ? self::stats() : null,
            'meeting' => $overseer || AccountSettings::showsDeferredPortals() ? self::nextMeeting($user) : null,
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

    private static function nextMeeting(User $user): ?Meeting
    {
        $meeting = Meeting::query()
            ->generalAssembly()
            ->where('status', MeetingStatus::Scheduled->value)
            ->whereNotNull('online_join_url')
            ->where('starts_at', '>', now()->subHours(self::RUNNING_HOURS))
            ->orderBy('starts_at')
            ->first();

        return $meeting !== null && $user->can('attend', $meeting) ? $meeting : null;
    }
}
