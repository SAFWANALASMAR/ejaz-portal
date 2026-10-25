<?php

namespace App\Console\Commands;

use App\Actions\Users\ChangeUserRoles;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * إنشاء حساب الإدارة الأول. يسأل عن البيانات تفاعلياً، فلا كلمة مرور في الشيفرة ولا في سجل الأوامر. الافتراضي المسؤول
 * التقني، لأنه يدير البوابة ويمنح الأدوار، والمشرف العام اطلاع فقط، والمدير التنفيذي مدير نظام كامل (المدير التنفيذي عبر
 * صفوان، 25 أكتوبر 2026). والبريد المسجل من قبل يُمنح الدور بلا كلمة مرور جديدة (حساب صفوان مسؤولاً تقنياً عند النشر).
 * يُشغَّل من الطرفية:
 *   docker compose exec app php artisan ejaz:create-admin
 *   docker compose exec app php artisan ejaz:create-admin --role=executive_director
 *   docker compose exec app php artisan ejaz:create-admin --role=super_admin
 */
#[Signature('ejaz:create-admin {--role=technical_admin : technical_admin أو executive_director أو super_admin}')]
#[Description('إنشاء حساب إدارة (المسؤول التقني، أو المدير التنفيذي، أو المشرف العام) أو منحه لحساب قائم (تفاعلي)')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $role = RoleName::tryFrom((string) $this->option('role'));

        if (! in_array($role, RoleName::overseers(), true)) {
            $this->error('الدور technical_admin أو executive_director أو super_admin وحدها.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $this->ask('البريد الإلكتروني')));
        // الحساب القائم صاحب البريد الموثق وحده: البريد غير الموثق قد يكون كتبه غير صاحبه عند التسجيل
        $existing = $email !== '' ? User::query()->where('email', $email)->whereNotNull('email_verified_at')->first() : null;

        // حساب قائم: يُمنح الدور، ويبقى بكلمة مروره، ويُسجَّل المنح ويُنبَّه به الرقباء كما في ChangeUserRoles
        if ($existing !== null) {
            if (! $existing->is_active || $existing->anonymized_at !== null) {
                $this->error('الحساب صاحب البريد موقوف أو مجهَّل، فلا يُمنح دوراً.');

                return self::FAILURE;
            }

            if (! $this->confirm('للبريد حساب قائم باسم «'.$existing->name.'». أيُمنح دور '.$role->getLabel().'؟')) {
                return self::FAILURE;
            }

            $overseers = ChangeUserRoles::activeOverseers();
            $before = $existing->getRoleNames()->sort()->values()->all();
            $existing->assignRole($role->value);
            $after = $existing->getRoleNames()->sort()->values()->all();

            activity('users')->performedOn($existing)->event(ActivityEvent::RolesChanged->value)
                ->withProperties(['old' => $before, 'new' => $after])
                ->log(ActivityEvent::RolesChanged->value);

            ChangeUserRoles::notifyOverseers($overseers, $existing, in_array($role->value, $before, true) ? [] : [$role->value], $after, null);

            $this->info('مُنح الحساب '.$email.' دور '.$role->getLabel().'. وإن لم يفعّل التحقق الثنائي فليفعّله، فهو شرط لدخول لوحة الإدارة.');

            return self::SUCCESS;
        }

        $name = (string) $this->ask('الاسم الكامل');
        $password = (string) $this->secret('كلمة المرور ('.__('account.password_rules.summary').')');
        $confirmation = (string) $this->secret('أعد كتابة كلمة المرور');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'min:3', 'max:120'],
                'email' => ['required', 'email:rfc', 'max:255', Rule::unique(User::class)->whereNotNull('email_verified_at')],
                'password' => PasswordPolicy::rules($name, $email, null),
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // البريد يُنشأ موثقاً، فيفقده ما يحمله من حسابات غير موثقة (أحقية الموثق — User::markEmailAsVerified)
        User::releaseFromUnverified('email', $email);
        $overseers = ChangeUserRoles::activeOverseers();

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($role->value);

        activity('security')->performedOn($user)->event(ActivityEvent::AdminCreated->value)->log(ActivityEvent::AdminCreated->value);
        ChangeUserRoles::notifyOverseers($overseers, $user, [$role->value], [$role->value], null);

        $this->info('أُنشئ حساب '.$role->getLabel().': '.$email);
        $this->line('سجّل الدخول من /login ثم فعّل التحقق الثنائي، فهو شرط لدخول لوحة الإدارة.');

        return self::SUCCESS;
    }
}
