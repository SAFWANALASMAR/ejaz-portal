<?php

namespace App\Console\Commands;

use App\Actions\Documents\SaveFormTemplate;
use App\Enums\RoleName;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;

/**
 * مستخدم تجريبي للبيئة المحلية فقط، لتجربة لوحة المستخدم ولقطات الشاشات.
 * كلمة المرور عشوائية في كل تشغيل، فلا كلمة مرور في الشيفرة.
 * وبالخيار --admin مشرف عام بتحقق ثنائي مفعّل ومفتاح عشوائي في كل تشغيل، ونموذج تجريبي بحقل توقيع،
 * لاختبار لوحة الإدارة في المتصفح (tests/browser/admin-spa.spec.mjs).
 */
#[Signature('ejaz:demo-user {--admin : مشرف عام بتحقق ثنائي} {--json : إخراج البيانات بصيغة JSON}')]
#[Description('إنشاء مستخدم تجريبي بكلمة مرور عشوائية (البيئة المحلية فقط)')]
class CreateDemoUser extends Command
{
    public const EMAIL = 'demo.member@ejaz.test';

    public const ADMIN_EMAIL = 'demo.admin@ejaz.test';

    public const DEMO_FORM_TITLE = 'نموذج تجريبي بحقل توقيع (للاختبار)';

    public function handle(TwoFactorAuthenticationProvider $twoFactor, SaveFormTemplate $saveTemplate): int
    {
        if (! $this->laravel->environment('local', 'testing')) {
            $this->error('هذا الأمر للبيئة المحلية فقط.');

            return self::FAILURE;
        }

        $admin = (bool) $this->option('admin');
        $email = $admin ? self::ADMIN_EMAIL : self::EMAIL;
        $password = Str::password(20);
        $secret = $admin ? $twoFactor->generateSecretKey() : null;

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $admin ? 'مشرف تجريبي' : 'مستخدم تجريبي',
            'phone' => $admin ? null : '+966500000001',
            'password' => $password,
            'email_verified_at' => now(),
            'phone_verified_at' => $admin ? null : now(),
            'privacy_accepted_at' => now(),
            'is_active' => true,
        ])->save();
        $user->syncRoles([($admin ? RoleName::TechnicalAdmin : RoleName::Member)->value]);

        if ($secret !== null) {
            $user->forceFill([
                'two_factor_secret' => encrypt($secret),
                'two_factor_recovery_codes' => encrypt((string) json_encode(Collection::times(8, fn () => RecoveryCode::generate())->all())),
                'two_factor_confirmed_at' => now(),
            ])->save();

            if (! FormTemplate::query()->where('title', self::DEMO_FORM_TITLE)->exists()) {
                $saveTemplate->handle($user, new FormTemplate, ['title' => self::DEMO_FORM_TITLE, 'is_active' => true, 'fields' => [
                    ['key' => 'name', 'label' => 'الاسم', 'type' => 'text'],
                    ['key' => 'sign', 'label' => 'التوقيع', 'type' => 'signature'],
                ]]);
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(array_filter(['email' => $email, 'password' => $password, 'totp_secret' => $secret])));
        } else {
            $this->info('المستخدم التجريبي: '.$email);
            $this->line('كلمة المرور لهذا التشغيل: '.$password);

            if ($secret !== null) {
                $this->line('مفتاح التحقق الثنائي لهذا التشغيل: '.$secret);
            }
        }

        return self::SUCCESS;
    }
}
