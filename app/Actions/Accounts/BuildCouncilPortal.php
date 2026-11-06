<?php

namespace App\Actions\Accounts;

use App\Enums\RoleName;
use App\Filament\Resources\AssemblyMemberships\AssemblyMembershipResource;
use App\Models\AssemblyMembership;
use App\Models\User;
use App\Support\AssemblyArea;
use App\Support\HomePortalStats;
use App\Support\PortalStats;

/**
 * بوابة عضو مجلس الإدارة في «حسابي» (ملاحظات المدير التنفيذي، ليلة 5 نوفمبر 2026، البند ٤ — decisions/2026-11-06-board-portal.md):
 * لوحة المستفيد نفسها، ومعها:
 * - «إحصائيات الموقع» مجمّعة من المحسوب في الخلفية (PortalStats وHomePortalStats)، فلا عدّ عند الطلب ولا بيانات شخصية.
 *   ولعضو الجمعية العمومية أيضاً.
 * - صف بطاقات للمجلس: إحصائيات الموقع، وأعضاء الجمعية العمومية، ومجلس الإدارة، والبث المباشر، وملفات الحوكمة. كل بطاقة إلى
 *   ما يُتاح لدوره أصلاً بسياسته، ولا تظهر بلا رابط.
 */
class BuildCouncilPortal
{
    /**
     * @return array{stats: array<string, int>|null, cards: list<array{key: string, url: string, external: bool, navigate: bool}>}
     */
    public function handle(User $user): array
    {
        $board = $user->hasRole(RoleName::BoardMember->value);

        return [
            'stats' => $board || $user->hasRole(RoleName::AssemblyMember->value) ? self::stats() : null,
            'cards' => $board ? self::cards($user) : [],
        ];
    }

    /**
     * @return array<string, int>|null
     */
    private static function stats(): ?array
    {
        $site = HomePortalStats::cached();
        $portal = PortalStats::cached();

        if ($site === null && $portal === null) {
            return null;
        }

        return array_filter([
            'visits' => $site['visits'] ?? null,
            'book_reads' => $site['book_reads'] ?? null,
            'media_views' => $site['media_views'] ?? null,
            'registrants' => $site['registrants'] ?? null,
            'programs' => $portal['programs'] ?? null,
            'members' => $portal['members'] ?? null,
        ], fn (?int $value): bool => $value !== null);
    }

    /**
     * @return list<array{key: string, url: string, external: bool, navigate: bool}>
     */
    private static function cards(User $user): array
    {
        $meeting = BuildOversight::nextMeeting($user);

        $cards = [
            ['key' => 'stats', 'url' => '#site-stats', 'external' => false, 'navigate' => false],
            ['key' => 'assembly_members', 'url' => $user->can('viewAny', AssemblyMembership::class) ? AssemblyMembershipResource::getUrl() : null, 'external' => false, 'navigate' => false],
            ['key' => 'board', 'url' => AssemblyArea::board($user) ? route('assembly.index') : null, 'external' => false, 'navigate' => true],
            // إلى رابط تيمز للاجتماع القادم أو الجاري الذي يراه، وإلا صفحة البث المباشر العامة
            ['key' => 'live', 'url' => $meeting?->online_join_url ?? route('live'), 'external' => $meeting !== null, 'navigate' => $meeting === null],
            ['key' => 'governance', 'url' => route('governance.index'), 'external' => false, 'navigate' => true],
        ];

        return array_values(array_filter($cards, fn (array $card): bool => $card['url'] !== null));
    }
}
