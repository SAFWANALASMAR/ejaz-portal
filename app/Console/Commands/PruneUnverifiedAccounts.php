<?php

namespace App\Console\Commands;

use App\Actions\Accounts\PruneUnverifiedAccounts as PruneAction;
use Illuminate\Console\Command;

/**
 * يحذف حسابات المستفيدين غير الموثقة بعد المدة، ما لم ترتبط بسجل أو بدور غير المستفيد
 * (App\Actions\Accounts\PruneUnverifiedAccounts — decisions.md 14 أكتوبر 2026).
 */
class PruneUnverifiedAccounts extends Command
{
    protected $signature = 'ejaz:prune-unverified-accounts';

    protected $description = 'حذف الحسابات التي لم يوثّق أصحابها جوالهم بعد المدة';

    public function handle(PruneAction $action): int
    {
        $count = $action->handle();

        $this->info("حُذف {$count} حساب غير موثق.");

        return self::SUCCESS;
    }
}
