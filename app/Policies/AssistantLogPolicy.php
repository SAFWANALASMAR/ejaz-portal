<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\AssistantLog;
use App\Models\User;

/**
 * سجل محادثات المساعد: الاطلاع وتعليم الإجابة الخاطئة بصلاحية ai_assistant.manage. ولا إنشاء ولا تعديل لنصه ولا
 * حذف يدوي لأحد، ومنهم المسؤول التقني، والمشرف العام يطّلع: يُحذف آلياً بعد مدة الاحتفاظ وحدها.
 */
class AssistantLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ai_assistant.manage') || RoleName::isOverseer($user);
    }

    public function view(User $user, AssistantLog $log): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    /**
     * تعليم الإجابة الخاطئة وملاحظتها وحدهما (FlagAssistantAnswer).
     */
    public function update(User $user, AssistantLog $log): bool
    {
        return $user->can('ai_assistant.manage');
    }

    public function delete(User $user, AssistantLog $log): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
