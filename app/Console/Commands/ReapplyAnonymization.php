<?php

namespace App\Console\Commands;

use App\Actions\Users\ReapplyAnonymization as ReapplyAnonymizationAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * بعد الاستعادة من نسخة احتياطية: يعيد إخفاء بيانات حساب أُخفيت بعد وقت النسخة. الأوامر تُولَّد من القاعدة
 * السابقة للاستعادة (scripts/restore.md)، حساباً حساباً بوقت إخفائه الأصلي، باسم مشرف له صلاحية الإخفاء.
 */
class ReapplyAnonymization extends Command
{
    protected $signature = 'ejaz:reapply-anonymization
        {user : رقم الحساب}
        {--at= : وقت الإخفاء الأصلي بتوقيت UTC، مثل 2026-09-01T10:00:00Z}
        {--actor= : بريد المشرف المنفّذ}';

    protected $description = 'إعادة إخفاء بيانات حساب بعد الاستعادة من نسخة احتياطية';

    public function handle(ReapplyAnonymizationAction $reapply): int
    {
        $actor = User::query()->where('email', mb_strtolower(trim((string) $this->option('actor'))))->where('is_active', true)->first();

        if ($actor === null) {
            $this->error('حدّد بريد مشرف فعّال بالخيار --actor.');

            return self::FAILURE;
        }

        try {
            $at = Carbon::parse((string) $this->option('at'), 'UTC')->utc();
        } catch (Throwable) {
            $at = null;
        }

        if ($this->option('at') === null || $at === null || $at->isFuture()) {
            $this->error('حدّد وقت الإخفاء الأصلي بالخيار --at، بتوقيت UTC وغير لاحق للآن.');

            return self::FAILURE;
        }

        $user = User::query()->find((int) $this->argument('user'));

        if ($user === null) {
            $this->info('الحساب غير موجود في القاعدة المستعادة، فلا بيانات له تُخفى.');

            return self::SUCCESS;
        }

        try {
            $done = $reapply->handle($actor, $user, $at);
        } catch (AuthorizationException) {
            $this->error('المشرف لا يملك صلاحية إخفاء البيانات.');

            return self::FAILURE;
        }

        $this->info($done ? "أُعيد إخفاء بيانات الحساب {$user->id}." : "بيانات الحساب {$user->id} مخفاة أصلاً.");

        return self::SUCCESS;
    }
}
