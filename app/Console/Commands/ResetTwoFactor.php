<?php

namespace App\Console\Commands;

use App\Actions\Users\ResetTwoFactorByAdmin;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * إعادة ضبط التحقق الثنائي من الطرفية على الخادم، لحساب لا يعيده له أحد من اللوحة: مدير النظام، فلا يعيد الضبط إلا من
 * هو أعلى رتبة (UserPolicy::resetTwoFactor — قرار المشرف، 3 نوفمبر 2026). والتنفيذ نفسه في ResetTwoFactorByAdmin::apply:
 * يُحذف السر والرموز وتاريخ التأكيد، وتُبطَل كلمة المرور برابط تعيينها إلى البريد الموثق، ويتجدد رمز «تذكّرني»، وتنتهي الجلسات، ويُسجَّل بمنفّذ «الطرفية» وسببه، ويُبلَّغ
 * صاحب الحساب. والدليل: docs/guides/two-factor-admin-reset.md.
 *   docker compose exec app php artisan ejaz:reset-two-factor admin@ejaz.sa --reason="..."
 *   docker compose exec -T app php artisan ejaz:reset-two-factor admin@ejaz.sa --reason="..." --force --no-interaction
 */
#[Signature('ejaz:reset-two-factor {email : بريد الحساب} {--reason= : سبب إعادة الضبط، يُحفظ في سجل العمليات} {--force : التنفيذ بلا سؤال التأكيد، ولازم مع --no-interaction}')]
#[Description('إعادة ضبط التحقق الثنائي لحساب فقد صاحبه تطبيق المصادقة (لمدير النظام خاصة)، بسجل وإبلاغ')]
class ResetTwoFactor extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = self::findByEmail($email);

        if ($user === null) {
            $this->error('لا حساب بهذا البريد.');

            return self::FAILURE;
        }

        if ($user->anonymized_at !== null) {
            $this->error('الحساب أُخفيت بياناته، فلا يُعاد ضبطه.');

            return self::FAILURE;
        }

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            $this->error('الحساب لم يفعّل التحقق الثنائي، فلا شيء يُعاد ضبطه.');

            return self::FAILURE;
        }

        // رابط تعيين كلمة المرور إلى البريد الموثق وحده (ResetTwoFactorByAdmin::ensureVerifiedEmail)
        if (! $user->hasVerifiedEmail()) {
            $this->error(__('users.reset_two_factor.no_verified_email'));

            return self::FAILURE;
        }

        $interactive = $this->input->isInteractive();
        $reason = $this->option('reason');

        if ($reason === null && $interactive) {
            $reason = $this->ask('سبب إعادة الضبط (يُحفظ في سجل العمليات، بلا رمز ولا كلمة مرور)');
        }

        $reason = trim((string) $reason);
        $validator = Validator::make(['reason' => $reason], ['reason' => ResetTwoFactorByAdmin::rules()['reason']], [], [
            'reason' => __('users.reset_two_factor.reason'),
        ]);

        if ($validator->fails()) {
            $this->error((string) $validator->errors()->first('reason'));

            return self::FAILURE;
        }

        // بلا طرفية تفاعلية لا سؤال: التأكيد بـ --force وحده
        if (! $this->option('force')
            && (! $interactive || ! $this->confirm('يُحذف التحقق الثنائي لحساب «'.$user->name.'» ('.$email.')، وتُبطَل كلمة مروره، وتنتهي كل جلساته، ويُرسل رابط تعيين كلمة المرور إلى بريده الموثق. أتابع؟'))) {
            $this->warn('لم يُنفَّذ شيء. وللتنفيذ بلا سؤال: --force.');

            return self::FAILURE;
        }

        ResetTwoFactorByAdmin::apply($user, null, $reason);

        $this->info('أُعيد ضبط التحقق الثنائي للحساب '.$email.'، وأُبطلت كلمة مروره، وانتهت جلساته.');
        $this->line('أُرسل رابط تعيين كلمة المرور إلى بريده الموثق، ولا دخول قبل تعيينها منه. ثم يفعّل التحقق الثنائي من جديد، فهو شرط لدخول لوحة الإدارة.');

        return self::SUCCESS;
    }

    /**
     * البريد قد يحمله أكثر من حساب غير موثق (التفرد للموثق وحده)، فالموثق أولاً، ثم الحساب الوحيد إن لم يكن غيره.
     */
    private static function findByEmail(string $email): ?User
    {
        if ($email === '') {
            return null;
        }

        $users = User::query()->where('email', $email)->get();

        return $users->first(fn (User $user): bool => $user->hasVerifiedEmail())
            ?? ($users->count() === 1 ? $users->first() : null);
    }
}
