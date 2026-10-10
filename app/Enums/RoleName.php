<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * الأدوار الثمانية في docs/roles-permissions.md.
 * القيمة النصية هي اسم الدور المخزن في جدول roles.
 */
enum RoleName: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case BoardMember = 'board_member';
    case Secretary = 'secretary';
    case ContentEditor = 'content_editor';
    case ProgramManager = 'program_manager';
    case Reviewer = 'reviewer';
    case AssemblyMember = 'assembly_member';
    case Member = 'member';
    // أدوار الوظائف (دليل ملفات الوظائف — requirements/11)، بأسماء ثابتة بين الفروع
    case ExecutiveDirector = 'executive_director';
    case FinancialManager = 'financial_manager';
    case Accountant = 'accountant';
    case ProjectsManager = 'projects_manager';
    case MarketingManager = 'marketing_manager';

    public function getLabel(): string
    {
        return __('roles.'.$this->value);
    }

    /**
     * الأدوار التي تدخل لوحة الإدارة.
     *
     * @return list<self>
     */
    public static function panelRoles(): array
    {
        return [
            self::SuperAdmin,
            self::BoardMember,
            self::Secretary,
            self::ContentEditor,
            self::ProgramManager,
            self::Reviewer,
        ];
    }

    /**
     * الأدوار التي يُشترط لها التحقق الثنائي (docs/security.md).
     *
     * @return list<self>
     */
    public static function twoFactorRoles(): array
    {
        return [
            self::SuperAdmin,
            self::BoardMember,
            self::Secretary,
            self::ContentEditor,
            self::ProgramManager,
            self::Reviewer,
        ];
    }

    /**
     * فئة «الموظفون»: أدوار الوظائف الست (requirements/11 و12).
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [
            self::ExecutiveDirector,
            self::FinancialManager,
            self::Accountant,
            self::ProjectsManager,
            self::MarketingManager,
            self::Secretary,
        ];
    }

    /**
     * @param  list<self>  $roles
     * @return list<string>
     */
    public static function values(array $roles): array
    {
        return array_map(fn (self $role): string => $role->value, $roles);
    }
}
