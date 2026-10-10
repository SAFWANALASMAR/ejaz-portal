<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * مستخدم تجريبي للبيئة المحلية فقط، لتجربة لوحة المستخدم ولقطات الشاشات.
 * كلمة المرور عشوائية في كل تشغيل، فلا كلمة مرور في الشيفرة.
 */
#[Signature('ejaz:demo-user {--json : إخراج البيانات بصيغة JSON}')]
#[Description('إنشاء مستخدم تجريبي بكلمة مرور عشوائية (البيئة المحلية فقط)')]
class CreateDemoUser extends Command
{
    public const EMAIL = 'demo.member@ejaz.test';

    public function handle(): int
    {
        if (! $this->laravel->environment('local', 'testing')) {
            $this->error('هذا الأمر للبيئة المحلية فقط.');

            return self::FAILURE;
        }

        $password = Str::password(20);

        $user = User::query()->firstOrNew(['email' => self::EMAIL]);
        $user->forceFill([
            'name' => 'مستخدم تجريبي',
            'phone' => '+966500000001',
            'password' => $password,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'privacy_accepted_at' => now(),
            'is_active' => true,
        ])->save();
        $user->syncRoles([RoleName::Member->value]);

        if ($this->option('json')) {
            $this->line((string) json_encode(['email' => self::EMAIL, 'password' => $password]));
        } else {
            $this->info('المستخدم التجريبي: '.self::EMAIL);
            $this->line('كلمة المرور لهذا التشغيل: '.$password);
        }

        return self::SUCCESS;
    }
}
