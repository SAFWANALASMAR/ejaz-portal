<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * سجل العمليات للقراءة فقط: لا إنشاء ولا تعديل ولا حذف لأحد، ولو كان مشرفاً عاماً.
 * يُسجَّل في AppServiceProvider لأن النموذج من مكتبة spatie/laravel-activitylog.
 */
class ActivityPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'activity';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
