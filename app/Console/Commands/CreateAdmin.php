<?php

namespace App\Console\Commands;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * إنشاء المشرف العام الأول. يسأل عن البيانات تفاعلياً، فلا كلمة مرور في الشيفرة
 * ولا في سجل الأوامر. يُشغَّل من الطرفية:
 *   docker compose exec app php artisan ejaz:create-admin
 */
#[Signature('ejaz:create-admin')]
#[Description('إنشاء حساب المشرف العام (تفاعلي)')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $name = (string) $this->ask('الاسم الكامل');
        $email = mb_strtolower(trim((string) $this->ask('البريد الإلكتروني')));
        $password = (string) $this->secret('كلمة المرور (10 أحرف على الأقل)');
        $confirmation = (string) $this->secret('أعد كتابة كلمة المرور');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'min:3', 'max:120'],
                'email' => ['required', 'email:rfc', 'max:255', Rule::unique(User::class)],
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(RoleName::SuperAdmin->value);

        activity('security')->performedOn($user)->event(ActivityEvent::AdminCreated->value)->log(ActivityEvent::AdminCreated->value);

        $this->info('أُنشئ حساب المشرف العام: '.$email);
        $this->line('سجّل الدخول من /login ثم فعّل التحقق الثنائي، فهو شرط لدخول لوحة الإدارة.');

        return self::SUCCESS;
    }
}
