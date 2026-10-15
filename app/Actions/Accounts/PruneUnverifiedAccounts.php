<?php

namespace App\Actions\Accounts;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * يحذف حسابات التسجيل الذاتي المتروكة: لم يوثّق صاحبها بريده ولا جواله قط، ولم يعد إليها بعد التسجيل،
 * بعد المدة (config/security.php — 14 يوماً)، فلا تبقى حسابات متروكة تحمل بيانات شخصية أو أرقاماً
 * (decisions.md — 14 أكتوبر 2026). والحذف نهائي، فلا يُحذف إلا الآمن:
 * - التسجيل الذاتي وحده (self_registered_at)، لا حساب أنشأه مشرف أو أمر.
 * - لم يوثّق بريده ولا جواله قط (first_verified_at)، فلا يُحذف موثق قديم غيّر جواله ولم يُدخل رمزه.
 * - لم يدخل بعد التسجيل: الدخول الآلي في طلب التسجيل نفسه لا يُعد عودة.
 * - لا حساب له دور غير «المستفيد» أو صلاحية مباشرة (حسابات اللوحة والموظفين).
 * - لا حساب مخفاة بياناته (له مدة احتفاظ خاصة به — ejaz:purge-anonymized-data).
 * - لا حساب يشير إليه أي سجل بمفتاح أجنبي، أياً كان جدوله: تسجيل في برنامج، أو طلب، أو مشاركة، أو
 *   استفسار، أو عضوية... والمفاتيح تُقرأ من القاعدة، فيشمل الفحص كل جدول يُضاف لاحقاً.
 *
 * وكل حساب يُعاد فحصه تحت قفل صفه قبل حذفه، فلا يُحذف حساب وُثّق أو ارتبط بسجل بعد اختياره.
 */
class PruneUnverifiedAccounts
{
    /**
     * الدخول الآلي بعد التسجيل يقع في الطلب نفسه، فما بعد هذه الثواني من التسجيل عودة من صاحب الحساب.
     */
    public const REGISTRATION_LOGIN_SECONDS = 60;

    public function handle(): int
    {
        $count = 0;
        $columns = $this->referencingColumns();

        $this->query($columns)->select('id')->lazyById()->each(function (User $user) use (&$count, $columns): void {
            if ($this->prune($user->id, $columns)) {
                $count++;
            }
        });

        return $count;
    }

    /**
     * يحذف الحساب إن بقيت شروطه تحت قفل صفه.
     *
     * @param  list<array{0: string, 1: string}>|null  $columns
     */
    public function prune(int $id, ?array $columns = null): bool
    {
        $columns ??= $this->referencingColumns();

        try {
            return DB::transaction(function () use ($id, $columns): bool {
                // القفل أولاً في استعلام مستقل، ثم الفحص بلقطة جديدة تقرأ ما ثُبّت قبل القفل. وسجل يُضاف بعده
                // ينتظر القفل، ثم يرفضه المفتاح الأجنبي لأن الحساب حُذف
                if (User::query()->whereKey($id)->lockForUpdate()->doesntExist()) {
                    return false;
                }

                $user = $this->query($columns)->whereKey($id)->first();

                if ($user === null) {
                    return false;
                }

                $user->notifications()->delete();
                DB::table('sessions')->where('user_id', $user->getKey())->delete();
                // حذف النموذج نفسه: يفك أدواره (HasRoles) ويُسجَّل في سجل العمليات
                $user->delete();

                return true;
            });
        } catch (QueryException $exception) {
            // سجل يشير إلى الحساب بمفتاح لم يُفحص: يبقى الحساب، ويكمل الأمر بقية الحسابات
            if ($exception->getCode() === '23503') {
                report($exception);

                return false;
            }

            throw $exception;
        }
    }

    /**
     * @param  list<array{0: string, 1: string}>|null  $columns
     * @return Builder<User>
     */
    public function query(?array $columns = null): Builder
    {
        $query = User::query()
            ->whereNotNull('self_registered_at')
            ->where('self_registered_at', '<', now()->subDays(max(1, (int) config('security.unverified_account_days'))))
            ->whereNull('first_verified_at')
            ->whereNull('email_verified_at')
            ->whereNull('phone_verified_at')
            ->whereNull('anonymized_at')
            ->where(fn (Builder $query) => $query->whereNull('last_login_at')
                ->orWhereRaw('last_login_at <= self_registered_at + make_interval(secs => ?)', [self::REGISTRATION_LOGIN_SECONDS]))
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', '!=', RoleName::Member->value))
            ->whereDoesntHave('permissions');

        foreach ($columns ?? $this->referencingColumns() as [$table, $column]) {
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
