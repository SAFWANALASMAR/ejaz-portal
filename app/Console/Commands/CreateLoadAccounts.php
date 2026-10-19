<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * حسابات اختبار التحمل (tests/load/README.md): موثّقة البريد والجوال، بكلمة مرور عشوائية واحدة لكل
 * تشغيل. للبيئة المحلية والتجريبية فقط، ولا تُنشأ في الإنتاج. والبريد على نطاق load.test المحجوز.
 */
#[Signature('ejaz:load-accounts {count : عدد الحسابات} {--json : إخراج كلمة المرور بصيغة JSON}')]
#[Description('إنشاء حسابات اختبار التحمل (المحلية والتجريبية فقط)')]
class CreateLoadAccounts extends Command
{
    public const DOMAIN = 'load.test';

    private const MAX = 50000;

    public function handle(): int
    {
        if (! $this->laravel->environment('local', 'testing', 'staging')) {
            $this->error('هذا الأمر للبيئة المحلية والتجريبية فقط.');

            return self::FAILURE;
        }

        // أرقام الحسابات بصيغة سعودية، فلا تُنشأ ومزود رسائل حقيقي مفعّل، فلا يصل إشعار إلى أرقام أشخاص
        if (MessagingSettings::sms()['provider'] !== 'log') {
            $this->error('شغّل الأمر ومزود الرسائل log فقط (SMS_DRIVER=log، ولا مزود في «قنوات التواصل»).');

            return self::FAILURE;
        }

        $count = (int) $this->argument('count');

        if ($count < 1 || $count > self::MAX) {
            $this->error('العدد بين 1 و'.number_format(self::MAX).'.');

            return self::FAILURE;
        }

        $password = Str::password(20);
        $hash = Hash::make($password);
        $now = now();
        $role = Role::findByName(RoleName::Member->value, 'web');

        // الحسابات السابقة تبقى بمشاركاتها، وتأخذ كلمة مرور هذا التشغيل، فيُعاد الاختبار بلا حذف
        DB::table('users')->where('email', 'like', '%@'.self::DOMAIN)->update(['password' => $hash, 'updated_at' => $now]);

        foreach (array_chunk(range(1, $count), 1000) as $chunk) {
            DB::transaction(function () use ($chunk, $hash, $now, $role): void {
                DB::table('users')->insertOrIgnore(array_map(fn (int $n): array => [
                    'name' => 'حساب اختبار '.$n,
                    'email' => 'load-'.$n.'@'.self::DOMAIN,
                    // أرقام في مدى لا يُستعمل للمستخدمين الفعليين في التجريبية
                    'phone' => '+96659'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
                    'password' => $hash,
                    'email_verified_at' => $now,
                    'phone_verified_at' => $now,
                    'privacy_accepted_at' => $now,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));

                $ids = DB::table('users')->whereIn('email', array_map(fn (int $n): string => 'load-'.$n.'@'.self::DOMAIN, $chunk))->pluck('id');

                DB::table('model_has_roles')->insertOrIgnore($ids->map(fn (int $id): array => ['role_id' => $role->id, 'model_type' => 'App\\Models\\User', 'model_id' => $id])->all());
            });
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(['count' => $count, 'domain' => self::DOMAIN, 'password' => $password]));
        } else {
            $this->info('أُنشئ '.number_format($count).' حساب: load-1@'.self::DOMAIN.' إلى load-'.$count.'@'.self::DOMAIN);
            $this->line('كلمة المرور لهذا التشغيل: '.$password);
        }

        return self::SUCCESS;
    }
}
