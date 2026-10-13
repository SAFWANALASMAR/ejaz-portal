<?php

namespace App\Enums;

use App\Models\User;
use Filament\Support\Contracts\HasLabel;

/**
 * الأدوار في docs/roles-permissions.md.
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

    // أدوار الوظائف (requirements/11 و12)
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
            self::ExecutiveDirector,
            self::FinancialManager,
            self::Accountant,
            self::ProjectsManager,
            self::MarketingManager,
        ];
    }

    /**
     * الأدوار التي يُشترط لها التحقق الثنائي (docs/security.md): كل دور يدخل اللوحة، فلا تختلف القائمتان.
     *
     * @return list<self>
     */
    public static function twoFactorRoles(): array
    {
        return self::panelRoles();
    }

    /**
     * فئة «الموظفون» في بوابات الدخول (requirements/12 §12).
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
     * الأدوار التي يمنحها الحساب ويسحبها (ChangeUserRoles). قاعدة في الشيفرة لا في ملف الصلاحيات:
     * - المشرف العام: كل الأدوار عدا عضو الجمعية العمومية (من سجل الأعضاء وحده).
     * - المدير التنفيذي («إدارة الموظفين»): الوظائف الأربع (المدير المالي، والمحاسب، ومدير المشاريع،
     *   ومدير التسويق) والمستفيد، وكلها صلاحياتها ضمن صلاحياته فلا يكسب بها ما لا يملك. فلا يمنح المشرف
     *   العام ولا عضو المجلس ولا المدير التنفيذي ولا المحكّم، ولا السكرتير ولا محرر المحتوى ولا مسؤول
     *   البرامج (فيها صلاحيات خارج صلاحياته: الطلبات والإعدادات والاستبيانات).
     * - غيرهما: لا شيء.
     *
     * @return list<self>
     */
    public static function assignableBy(User $actor): array
    {
        if ($actor->hasRole(self::SuperAdmin->value)) {
            return array_values(array_filter(self::cases(), fn (self $role): bool => $role !== self::AssemblyMember));
        }

        if ($actor->hasRole(self::ExecutiveDirector->value)) {
            return [self::FinancialManager, self::Accountant, self::ProjectsManager, self::MarketingManager, self::Member];
        }

        return [];
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
