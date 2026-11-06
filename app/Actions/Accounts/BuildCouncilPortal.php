<?php

namespace App\Actions\Accounts;

use App\Enums\MeetingBody;
use App\Enums\RoleName;
use App\Filament\Resources\AssemblyMemberships\AssemblyMembershipResource;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Models\AssemblyMembership;
use App\Models\LiveStream;
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
        // ولا يُطلب الاجتماع أصلاً لدخول لا يكفي لما خلف التحقق الثنائي (بلا تفعيل، أو جلسة من «تذكّرني»)، فلا يصل إلى «حسابي»
        // شيء منه (المشرف على #197، المنخفضة ٣)
        $meeting = EnsureTwoFactorIsEnabled::satisfied($user) ? BuildOversight::nextMeeting($user) : null;

        $cards = [
            ['key' => 'stats', 'url' => '#site-stats', 'external' => false, 'navigate' => false],
            ['key' => 'assembly_members', 'url' => $user->can('viewAny', AssemblyMembership::class) ? AssemblyMembershipResource::getUrl() : null, 'external' => false, 'navigate' => false],
            ['key' => 'board', 'url' => AssemblyArea::board($user) ? route('assembly.index') : null, 'external' => false, 'navigate' => true],
            // إلى صفحة الاجتماع القادم أو الجاري في منطقة الأعضاء، خلف التحقق الثنائي، لا إلى رابط تيمز الخام: «حسابي» بلا التحقق
            // الثنائي، والرابط سرّ (المراجعة الأمنية على #197، العالية). وإلا صفحة البث المباشر العامة
            // وصفحة الاجتماع لاجتماع الجمعية العمومية وحده (MeetingPolicy::attend)، واجتماع المجلس أو اللجنة في صفحة المنطقة.
            // وبلا اجتماع: صفحة البث متى كان بث منشور (وإلا ترد 404)، ثم صفحة الاجتماعات، وإلا لا بطاقة (قبول 6 نوفمبر)
            ['key' => 'live', 'url' => match (true) {
                $meeting !== null && $user->can('accessAssemblyArea') => $meeting->body === MeetingBody::GeneralAssembly
                    ? route('assembly.meetings.show', $meeting)
                    : route('assembly.index'),
                LiveStream::query()->current()->exists() => route('live'),
                $user->can('accessAssemblyArea') => route('assembly.meetings.index'),
                default => null,
            }, 'external' => false, 'navigate' => true],
            ['key' => 'governance', 'url' => route('governance.index'), 'external' => false, 'navigate' => true],
        ];

        return array_values(array_filter($cards, fn (array $card): bool => $card['url'] !== null));
    }
}
