<?php

namespace App\Policies;

use App\Models\AccountCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * بطاقات لوحة المستفيد (صلاحيات account_cards.*): مديرو النظام. وبطاقة القسم المبني تُخفى ولا تُحذف، فتعود بإظهارها.
 */
class AccountCardPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'account_cards';
    }

    public function delete(User $user, Model $record): bool
    {
        return $record instanceof AccountCard && $record->route === null && parent::delete($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
