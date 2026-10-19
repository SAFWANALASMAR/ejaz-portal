<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * الأسئلة الشائعة للمساعد: كل العمليات بصلاحية ai_assistant.manage (المشرف العام وحده).
 */
class AssistantFaqPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'ai_assistant';
    }

    protected function allows(User $user, string $action): bool
    {
        return $user->can('ai_assistant.manage');
    }

    public function restore(User $user, Model $record): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
