<?php

namespace App\Actions\Users;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Support\LoginIdentifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * معالجة طلب حذف الحساب (حق الحذف في نظام حماية البيانات الشخصية).
 *
 * تُخفى البيانات الشخصية ويوقف الحساب، ويبقى السجل نفسه لما يلزم الاحتفاظ به نظاماً
 * (الطلبات والتسجيلات المرتبطة به). وتُمسح آثار هويته من المخازن الأخرى:
 * سجل العمليات (القيم، والعناوين، وبصمة المعرّف)، وروابط الاستعادة، والإشعارات.
 * لا تراجع عنها، فلا تُنفَّذ إلا على حساب طلب صاحبه الحذف.
 */
class AnonymizeUser
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $user): void
    {
        // شروط الإجراء نفسه تُفحص صراحة، فلا يتجاوزها أي منح عام للصلاحيات
        if (! self::eligible($actor, $user) || ! $actor->can('anonymize', $user)) {
            throw new AuthorizationException;
        }

        $oldEmail = $user->email;
        $oldPhone = $user->phone;

        DB::transaction(function () use ($actor, $user, $oldEmail, $oldPhone): void {
            // الحفظ دون التسجيل الآلي، وإلا كتب السجل القيم القديمة من جديد
            activity()->withoutLogging(function () use ($user): void {
                $user->syncRoles([]);

                $user->forceFill([
                    'name' => __('users.anonymized_name'),
                    'email' => 'deleted-'.$user->id.'@deleted.invalid',
                    'phone' => null,
                    'email_verified_at' => null,
                    'phone_verified_at' => null,
                    'password' => Str::password(32),
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                    'remember_token' => null,
                    'is_active' => false,
                    'anonymized_at' => now(),
                ])->save();
            });

            $this->scrubActivityLog($user, $oldEmail, $oldPhone);

            DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
            $user->notifications()->delete();

            activity('users')
                ->causedBy($actor)
                ->performedOn($user)
                ->event(ActivityEvent::Anonymized->value)
                ->log(ActivityEvent::Anonymized->value);
        });
    }

    /**
     * حساب طلب صاحبه الحذف، ولم تُخفَ بياناته بعد، وليس حساب المنفّذ.
     */
    public static function eligible(User $actor, User $user): bool
    {
        return $actor->isNot($user)
            && $user->deletion_requested_at !== null
            && $user->anonymized_at === null;
    }

    /**
     * يبقى من السجل ما حدث ومتى ومن نفّذه، وتُمسح منه هوية صاحب الحساب:
     * قيم الحقول في سجلاته، والعنوان ونوع المتصفح، وبصمة معرّف الدخول.
     */
    private function scrubActivityLog(User $user, ?string $oldEmail, ?string $oldPhone): void
    {
        $hashes = array_values(array_filter([LoginIdentifier::hash($oldEmail), LoginIdentifier::hash($oldPhone)]));

        Activity::query()
            ->where(fn ($query) => $query
                ->whereMorphedTo('subject', $user)
                ->orWhereMorphedTo('causer', $user)
                ->when($hashes !== [], fn ($query) => $query->orWhereIn('properties->identifier_hash', $hashes)))
            // بالمعرّف لا بالإزاحة: المسح يُخرج السجل من نتيجة الاستعلام، فتنزلق الصفحات بالإزاحة
            ->eachById(function (Activity $log) use ($user): void {
                $aboutUser = $log->subject_type === $user->getMorphClass() && (int) $log->subject_id === $user->id;

                $log->forceFill([
                    'attribute_changes' => $aboutUser ? null : $log->attribute_changes,
                    'properties' => collect($log->properties)->except(['ip', 'agent', 'identifier_hash']),
                ])->saveQuietly();
            });
    }
}
