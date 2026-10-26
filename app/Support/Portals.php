<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Filament\Resources\ArchiveItems\ArchiveItemResource;
use App\Filament\Resources\AssemblyFiles\AssemblyFileResource;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\FormTemplates\FormTemplateResource;
use App\Filament\Resources\Meetings\MeetingResource;
use App\Filament\Resources\Programs\ProgramResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Resource as FilamentResource;

/**
 * بوابات اللوحة (requirements/12 §12): لكل فئة أدوارها وقدراتها بنصوص المتطلبات (lang/ar/portals.php)،
 * وكل قدرة شاشتها. والرابط لا يظهر إلا لمن يملك فتح الشاشة (سياستها)، فالصلاحيات هي الحَكَم لا هذه القائمة.
 * والمشتركون بوابتهم لوحة المستخدم (/account) لا اللوحة.
 */
final class Portals
{
    /**
     * البوابات المؤجلة بقرار المدير التنفيذي (AccountSettings::showsDeferredPortals — 25 أكتوبر 2026).
     */
    public const DEFERRED = ['board', 'employees'];

    /**
     * @return array<string, array{roles: list<RoleName>, items: array<string, class-string<FilamentResource>|null>}>
     */
    public static function definitions(): array
    {
        return [
            'board' => [
                'roles' => [RoleName::BoardMember],
                'items' => ['meetings' => MeetingResource::class, 'reports' => DocumentResource::class, 'files' => AssemblyFileResource::class],
            ],
            'executive' => [
                'roles' => [RoleName::ExecutiveDirector],
                // «الإحصائيات العامة» في هذه الصفحة نفسها (عنصر الإحصائيات)
                'items' => ['employees' => UserResource::class, 'programs' => ProgramResource::class, 'competitions' => CompetitionResource::class, 'files' => ArchiveItemResource::class, 'stats' => null],
            ],
            'employees' => [
                'roles' => array_values(array_filter(RoleName::staff(), fn (RoleName $role): bool => $role !== RoleName::ExecutiveDirector)),
                'items' => ['upload' => ArchiveItemResource::class, 'attendance' => ProgramResource::class, 'forms' => FormTemplateResource::class, 'programs' => ProgramResource::class],
            ],
            'reviewers' => [
                'roles' => [RoleName::Reviewer],
                'items' => ['reviews' => ReviewResource::class],
            ],
        ];
    }

    /**
     * بوابات الحساب بقدراتها، والرابط لما يملك فتحه.
     *
     * @return list<array{key: string, items: list<array{key: string, url: ?string}>}>
     */
    public static function for(User $user): array
    {
        $portals = [];

        foreach (self::definitions() as $key => $portal) {
            if (! $user->hasAnyRole(RoleName::values($portal['roles']))) {
                continue;
            }

            // بوابتا مجلس الإدارة والموظفين مؤجلتان بقرار المدير التنفيذي حتى تُظهرا من اللوحة، والصلاحيات كما هي
            if (in_array($key, self::DEFERRED, true) && ! AccountSettings::showsDeferredPortals()) {
                continue;
            }

            $items = [];

            foreach ($portal['items'] as $item => $resource) {
                $items[] = ['key' => $item, 'url' => $resource !== null && $user->can('viewAny', $resource::getModel()) ? $resource::getUrl() : null];
            }

            $portals[] = ['key' => $key, 'items' => $items];
        }

        return $portals;
    }
}
