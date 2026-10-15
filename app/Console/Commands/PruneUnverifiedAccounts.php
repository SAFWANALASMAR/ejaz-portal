<?php

namespace App\Console\Commands;

use App\Actions\Accounts\PruneUnverifiedAccounts as PruneAction;
use Illuminate\Console\Command;

/**
 * يحذف حسابات التسجيل الذاتي المتروكة: لم توثَّق قط ولم يعد إليها أصحابها، بعد المدة، ما لم ترتبط بسجل أو بدور غير المستفيد
 * (App\Actions\Accounts\PruneUnverifiedAccounts — decisions.md 14 أكتوبر 2026).
 */
class PruneUnverifiedAccounts extends Command
{
    protected $signature = 'ejaz:prune-unverified-accounts';

    protected $description = 'حذف حسابات التسجيل الذاتي المتروكة التي لم توثَّق قط بعد المدة';

    public function handle(PruneAction $action): int
    {
        $count = $action->handle();

        $this->info("حُذف {$count} حساب غير موثق.");

        return self::SUCCESS;
    }
}
