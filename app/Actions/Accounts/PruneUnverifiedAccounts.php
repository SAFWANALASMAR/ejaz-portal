<?php

namespace App\Actions\Accounts;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * يحذف حسابات المستفيدين التي لم يوثّق أصحابها جوالهم بعد المدة (config/security.php — 14 يوماً)،
 * فلا تبقى حسابات متروكة تحمل بيانات شخصية أو أرقاماً (decisions.md — 14 أكتوبر 2026).
 *
 * لا يُحذف إلا الآمن:
 * - لا حساب له دور غير «المستفيد» أو صلاحية مباشرة (حسابات اللوحة والموظفين).
 * - لا حساب مخفاة بياناته (له مدة احتفاظ خاصة به — ejaz:purge-anonymized-data).
 * - لا حساب يشير إليه أي سجل بمفتاح أجنبي، أياً كان جدوله: تسجيل في برنامج، أو طلب، أو مشاركة، أو
 *   استفسار، أو عضوية... والمفاتيح تُقرأ من القاعدة، فيشمل الفحص كل جدول يُضاف لاحقاً.
 */
class PruneUnverifiedAccounts
{
    public function handle(): int
    {
        $count = 0;

        $this->query()->lazyById()->each(function (User $user) use (&$count): void {
            DB::transaction(function () use ($user): void {
                $user->notifications()->delete();
                DB::table('sessions')->where('user_id', $user->getKey())->delete();
                // حذف النموذج نفسه: يفك أدواره (HasRoles) ويُسجَّل في سجل العمليات
                $user->delete();
            });
            $count++;
        });

        return $count;
    }

    /**
     * @return Builder<User>
     */
    public function query(): Builder
    {
        $query = User::query()
            ->whereNull('phone_verified_at')
            ->whereNull('anonymized_at')
            ->where('created_at', '<', now()->subDays(max(1, (int) config('security.unverified_account_days'))))
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', '!=', RoleName::Member->value))
            ->whereDoesntHave('permissions');

        foreach ($this->referencingColumns() as [$table, $column]) {
            $query->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from($table)->whereColumn("{$table}.{$column}", 'users.id'));
        }

        return $query;
    }

    /**
     * كل عمود في القاعدة يشير بمفتاح أجنبي إلى users.id.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function referencingColumns(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT src.relname AS table_name, att.attname AS column_name
            FROM pg_constraint con
            JOIN pg_class src ON src.oid = con.conrelid
            JOIN pg_class dst ON dst.oid = con.confrelid
            JOIN pg_namespace ns ON ns.oid = src.relnamespace
            JOIN LATERAL unnest(con.conkey) AS k(attnum) ON true
            JOIN pg_attribute att ON att.attrelid = con.conrelid AND att.attnum = k.attnum
            WHERE con.contype = 'f' AND dst.relname = 'users' AND ns.nspname = current_schema()
            SQL);

        return array_map(fn (object $row) => [(string) $row->table_name, (string) $row->column_name], $rows);
    }
}
