<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\LoginIdentifier;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'privacy_accepted_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, TwoFactorAuthenticatable;

    /**
     * القيم الافتراضية مطابقة لقاعدة البيانات، حتى تتوفر في النموذج قبل إعادة قراءته.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasAnyRole(RoleName::values(RoleName::panelRoles()));
    }

    /**
     * الأدوار الإدارية الحساسة لا تدخل اللوحة إلا بعد تفعيل التحقق الثنائي.
     */
    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole(RoleName::values(RoleName::twoFactorRoles()));
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone !== null && $this->phone_verified_at !== null;
    }

    /**
     * الحساب صاحب معرّف الدخول: البريد، أو الجوال بأي صيغة.
     */
    public static function findByLoginIdentifier(?string $identifier): ?self
    {
        $login = LoginIdentifier::normalize($identifier);

        return match (true) {
            $login === '' => null,
            LoginIdentifier::isEmail($login) => self::query()->where('email', $login)->first(),
            // الرقم قد يحمله حساب غير موثق مع صاحبه الموثق، فالموثق أولاً (decisions.md — 14 أكتوبر 2026)
            str_starts_with($login, '+966') => self::query()->where('phone', $login)
                ->orderByRaw('phone_verified_at IS NULL')->orderBy('id')->first(),
            default => null,
        };
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * البيانات الشخصية لا تُسجَّل قيمها في سجل العمليات، بل أسماء ما تغيّر منها فقط
     * (حق الحذف في نظام حماية البيانات الشخصية — docs/decisions.md).
     */
    public const PERSONAL_FIELDS = ['name', 'email', 'phone'];

    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            $changed = array_values(array_intersect(self::PERSONAL_FIELDS, array_keys($user->getChanges())));

            if ($changed !== []) {
                activity('users')
                    ->performedOn($user)
                    ->event(ActivityEvent::PersonalDataChanged->value)
                    ->withProperties(['fields' => $changed])
                    ->log(ActivityEvent::PersonalDataChanged->value);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        // لا كلمة المرور، ولا رموز التحقق الثنائي، ولا قيم البيانات الشخصية
        return LogOptions::defaults()
            ->logOnly(['email_verified_at', 'phone_verified_at', 'is_active', 'deletion_requested_at', 'anonymized_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * النماذج المعبأة المحفوظة في ملف صاحب الحساب (FormEntry::person).
     *
     * @return HasMany<FormEntry, $this>
     */
    public function formEntries(): HasMany
    {
        return $this->hasMany(FormEntry::class, 'person_id');
    }
}
