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
    // المشرف العام اطلاع فقط، والمسؤول التقني يملك ما كان للمشرف العام (المدير التنفيذي عبر صفوان، 25 أكتوبر 2026)
    case SuperAdmin = 'super_admin';
    case TechnicalAdmin = 'technical_admin';
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
            self::TechnicalAdmin,
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
     * مديرو النظام: يملكون الإضافة والتعديل والحذف في كل شيء، والإعدادات التقنية، ومنح الأدوار. المدير التنفيذي «أدمن
     * كامل»، والمسؤول التقني (المدير التنفيذي عبر صفوان، 25 أكتوبر 2026).
     *
     * @return list<self>
     */
    public static function administrators(): array
    {
        return [self::ExecutiveDirector, self::TechnicalAdmin];
    }

    public static function isAdministrator(User $user): bool
    {
        return $user->hasAnyRole(self::values(self::administrators()));
    }

    /**
     * من يطّلع على كل شيء في اللوحة بلا قيد جهة أو تصنيف، ويصله كل تنبيه أمني وتشغيلي: المشرف العام (اطلاعاً) ومديرو
     * النظام.
     *
     * @return list<self>
     */
    public static function overseers(): array
    {
        return [self::SuperAdmin, ...self::administrators()];
    }

    public static function isOverseer(User $user): bool
    {
        return $user->hasAnyRole(self::values(self::overseers()));
    }

    /**
     * الأدوار التي يمنحها الحساب ويسحبها (ChangeUserRoles). قاعدة في الشيفرة لا في ملف الصلاحيات:
     * - مديرو النظام (المدير التنفيذي والمسؤول التقني): كل الأدوار عدا عضو الجمعية العمومية (من سجل الأعضاء وحده).
     * - المشرف العام: لا شيء، فهو اطلاع فقط.
     * - غيرهم: لا شيء.
     *
     * @return list<self>
     */
    public static function assignableBy(User $actor): array
    {
        if (self::isAdministrator($actor)) {
            return array_values(array_filter(self::cases(), fn (self $role): bool => $role !== self::AssemblyMember));
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
