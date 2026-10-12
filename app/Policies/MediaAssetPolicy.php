<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * مكتبة الوسائط: الاطلاع وتعديل العنوان والنص البديل. والإضافة بأمر الاستيراد وحده، ولا حذف من
 * اللوحة، لأن نسخها موضوعة في صفحات الموقع.
 */
class MediaAssetPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'media_assets';
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
