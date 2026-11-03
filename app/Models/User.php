<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\AccountSettings;
use App\Support\AssemblyMembersCount;
use App\Support\LoginIdentifier;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property Carbon|null $sessions_revoked_at
 * @property Carbon|null $must_set_password_at
 */
#[Fillable(['name', 'username', 'email', 'phone', 'password', 'privacy_accepted_at'])]
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
        'username' => null,
        'is_active' => true,
        'password_upgrade_required_at' => null,
        'password_set_by_admin_at' => null,
        'reading_tracking_disabled_at' => null,
        'sessions_revoked_at' => null,
        'must_set_password_at' => null,
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
            'self_registered_at' => 'datetime',
            'first_verified_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'anonymized_at' => 'datetime',
            // «لا تحفظ قراءاتي» (docs/decisions/2026-11-01-my-reading.md)
            'reading_tracking_disabled_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password_upgrade_required_at' => 'datetime',
            'password_set_by_admin_at' => 'datetime',
            // إنهاء الجلسات كلها من اللوحة (ResetTwoFactorByAdmin — EnsureAccountIsActive)
            'sessions_revoked_at' => 'datetime',
            // يلزمه تعيين كلمة مرور برابط البريد الموثق قبل أي دخول (ResetTwoFactorByAdmin — ResetUserPassword)
            'must_set_password_at' => 'datetime',
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

    public function hasVerifiedEmail(): bool
    {
        return $this->email !== null && $this->email_verified_at !== null;
    }

    /**
     * الحساب يستوفي التوثيق المطلوب للتسجيل في البرامج وتقديم الطلبات: لا شيء ما دام التوثيق مطفأً من اللوحة
     * (AccountSettings — ملاحظات المدير التنفيذي، 25 أكتوبر 2026)، وإلا بريد موثق أو جوال موثق.
     */
    public function isVerified(): bool
    {
        return ! AccountSettings::verificationEnabled() || $this->hasVerifiedEmail() || $this->hasVerifiedPhone();
    }

    /**
     * ما يُعرض به الحساب في التنبيهات والسجلات: اسم المستخدم، ثم البريد، ثم الجوال.
     */
    public function identifier(): string
    {
        return $this->username ?? $this->email ?? $this->phone ?? '#'.$this->getKey();
    }

    /**
     * رابط رمز QR لتطبيق المصادقة باسم الحساب. وفي Fortify يُسمّى بـ Fortify::username()، وهو هنا حقل نموذج الدخول
     * (login) لا عمود، فكان يرمي خارج الإنتاج ويُنتج رمزاً بلا اسم فيه (الاختبار الشامل 0.14).
     */
    public function twoFactorQrCodeUrl(): string
    {
        return app(TwoFactorAuthenticationProvider::class)->qrCodeUrl(
            (string) config('app.name'),
            $this->identifier(),
            Fortify::currentEncrypter()->decrypt($this->two_factor_secret)
        );
    }

    /**
     * الحساب صاحب معرّف الدخول: البريد، أو الجوال بأي صيغة، أو اسم المستخدم.
     */
    public static function findByLoginIdentifier(?string $identifier): ?self
    {
        $login = LoginIdentifier::normalize($identifier);

        return match (true) {
            $login === '' => null,
            LoginIdentifier::isEmail($login) => self::soleHolder('email', $login),
            str_starts_with($login, '+966') => self::soleHolder('phone', $login),
            LoginIdentifier::isUsername($login) => self::query()->where('username', $login)->first(),
            default => null,
        };
    }

    /**
     * صاحب البريد أو الجوال عند الدخول: الموثق أولاً، فالقيمة قد يحملها حساب غير موثق مع صاحبها (decisions.md — 14
     * أكتوبر 2026). وبلا موثق يُقبل الحساب غير الموثق ما دام وحده، فإن اشترك فيها أكثر من حساب لم يُعرف صاحبها، فيدخل
     * كلٌّ باسم مستخدمه (مراجعة المشرف المبكرة، 26 أكتوبر 2026).
     */
    private static function soleHolder(string $column, string $value): ?self
    {
        $holders = self::query()->where($column, $value)->orderByRaw($column.'_verified_at IS NULL')->orderBy('id')->limit(2)->get();
        $first = $holders->first();

        return match (true) {
            $first === null => null,
            $first->getAttribute($column.'_verified_at') !== null, $holders->count() === 1 => $first,
            default => null,
        };
    }

    /**
     * البريد الموثق لحساب واحد (فهرس تفرد جزئي)، والحسابات غير الموثقة التي تحمله تفقده، فلا يحجز حساب غير موثق بريد
     * صاحبه والتوثيق مطفأ (مراجعة المشرف المبكرة، 26 أكتوبر 2026). وبريد موثق في حساب آخر لا يُوثَّق ثانية.
     */
    public function markEmailAsVerified(): bool
    {
        if ($this->email === null) {
            return false;
        }

        try {
            return DB::transaction(function (): bool {
                $holders = self::query()->where('email', $this->email)->whereKeyNot($this->getKey());

                if ((clone $holders)->whereNotNull('email_verified_at')->exists()) {
                    return false;
                }

                self::releaseFromUnverified('email', (string) $this->email, $this->getKey());

                return $this->forceFill(['email_verified_at' => $this->freshTimestamp()])->save();
            });
        } catch (UniqueConstraintViolationException) {
            // حسابان وثّقا البريد نفسه في اللحظة نفسها
            return false;
        }
    }

    /**
     * يُسقط البريد أو الجوال عن الحسابات غير الموثقة التي تحمله، حين يأخذه حساب موثق (التوثيق، أو إنشاء حساب موظف). ولا
     * يُسقط عن حساب لا معرّف دخول آخر له (الحسابات القديمة بلا اسم مستخدم)، فلا يُغلق.
     */
    public static function releaseFromUnverified(string $column, string $value, int|string|null $except = null): void
    {
        $other = $column === 'email' ? 'phone' : 'email';

        self::query()->where($column, $value)->whereNull($column.'_verified_at')
            ->where(fn ($query) => $query->whereNotNull('username')->orWhereNotNull($other))
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))
            ->update([$column => null]);
    }

    /**
     * البريد مأخوذ إن حمله حساب موثق البريد، أو حساب قديم بلا اسم مستخدم: بريده معرّف دخوله الوحيد، فلو أخذه غيره لم يعرف
     * صاحبه من يُدخل (soleHolder) فيُغلق حسابه (مراجعة المشرف، M1 — 26 أكتوبر 2026).
     */
    public static function emailTakenRule(int|string|null $ignore = null): Unique
    {
        return Rule::unique(self::class, 'email')
            ->where(fn ($query) => $query->whereNotNull('email_verified_at')->orWhereNull('username'))
            ->ignore($ignore);
    }

    /**
     * رابط التوثيق لمن له بريد. وإرساله الآلي بعد التسجيل يتبع مفتاح التوثيق في اللوحة (SendVerificationWhenRequired)،
     * وطلبه من صاحب الحساب يرسله دائماً.
     */
    public function sendEmailVerificationNotification(): void
    {
        if ($this->email !== null) {
            $this->notify(new VerifyEmailNotification);
        }
    }

    /**
     * الاستعادة بالبريد للبريد الموثق وحده (ملاحظات المدير التنفيذي، 25 أكتوبر 2026)، فلا يستعيد حساباً من كتب بريد
     * غيره عند التسجيل. والرد للطالب واحد في كل حال (NeutralPasswordResetLinkResponse). ومن لا بريد موثقاً له ولا جوالاً
     * يعيد له المدير التنفيذي أو المسؤول التقني كلمة المرور من اللوحة.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        if ($this->hasVerifiedEmail()) {
            $this->notify(new ResetPasswordNotification($token));
        }
    }

    /**
     * البيانات الشخصية لا تُسجَّل قيمها في سجل العمليات، بل أسماء ما تغيّر منها فقط
     * (حق الحذف في نظام حماية البيانات الشخصية — docs/decisions.md).
     */
    public const PERSONAL_FIELDS = ['name', 'username', 'email', 'phone'];

    protected static function booted(): void
    {
        // أول توثيق للبريد أو الجوال يبقى ولو تغيّر أحدهما بعده، فلا يُعد الموثق قديماً غير موثق (PruneUnverifiedAccounts)
        static::saving(function (User $user): void {
            if ($user->first_verified_at === null && ($user->email_verified_at !== null || $user->phone_verified_at !== null)) {
                $user->first_verified_at = now();
            }
        });

        static::updated(function (User $user): void {
            // الحساب المعطّل أو المجهَّل يخرج من عدد أعضاء الجمعية العمومية الفعّالين (AssemblyMembersCount)
            if ($user->wasChanged(['is_active', 'anonymized_at'])) {
                AssemblyMembersCount::forget();
            }

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
