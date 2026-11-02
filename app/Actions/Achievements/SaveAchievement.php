<?php

namespace App\Actions\Achievements;

use App\Enums\AchievementStatus;
use App\Models\Achievement;
use App\Models\User;
use App\Rules\PlainText;
use App\Support\PrivateUpload;
use App\Support\StoragePath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * إضافة إنجاز أو تعديله أو نشره (AchievementPolicy — الإدارة التنفيذية وحدها). المرفق اختياري: PDF حقيقي أو صورة يُعاد
 * ترميزها (PrivateUpload) في مجلده بالقرص الخاص، باسم عشوائي من حقل الرفع وبسقف الحجم. واسم التنزيل امتداده من نوع الملف
 * المفحوص. ومسار إنجاز آخر لا يُعاد استعماله (وقيد فريد في القاعدة)، والمرفوع المرفوض يُحذف، والمستبدَل أو المُزال يُحذف بعد
 * الحفظ. ويُسجَّل الحفظ (LogsActivity في سجل المحتوى).
 */
class SaveAchievement
{
    public const MIN_DATE = '2000-01-01';

    /**
     * @param  array<string, mixed>  $data  title، وachieved_on، وdetails، وstatus، وattachment، وattachment_name
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Achievement $achievement, array $data): Achievement
    {
        $previous = $achievement->exists ? $achievement->attachment : null;
        $incoming = is_string($data['attachment'] ?? null) && $data['attachment'] !== '' ? $data['attachment'] : null;

        if (! ($achievement->exists ? $actor->can('update', $achievement) : $actor->can('create', Achievement::class))) {
            self::discard($incoming, $previous);

            throw new AuthorizationException;
        }

        // خطأ في حقل نصي لا يحذف المرفوع: يبقى في النموذج ليُحفظ بعد التصحيح، والمرفق المرفوض يحذفه فحصه (check)
        $validated = Validator::make($data, self::rules())->validate();

        $achievement->fill([
            'title' => $validated['title'],
            'achieved_on' => $validated['achieved_on'],
            'details' => $validated['details'],
            'status' => $validated['status'],
        ]);

        if ($incoming !== $previous) {
            if ($incoming === null) {
                $achievement->forceFill(['attachment' => null, 'attachment_name' => null, 'attachment_size' => null]);
            } else {
                $checked = self::check($incoming, $previous);
                $achievement->forceFill([
                    'attachment' => $incoming,
                    'attachment_name' => PrivateUpload::downloadName((string) ($validated['attachment_name'] ?? ''), $checked['extension']),
                    'attachment_size' => $checked['size'],
                ]);
            }
        }

        $achievement->updated_by = $actor->id;

        if (! $achievement->exists) {
            $achievement->created_by = $actor->id;
        }

        try {
            $achievement->save();
        } catch (UniqueConstraintViolationException) {
            // إنجاز آخر حُفظ بالمسار نفسه في اللحظة نفسها: يبقى الملف له
            throw ValidationException::withMessages(['attachment' => __('validation.file_in_use')]);
        }

        if ($previous !== null && $previous !== $achievement->attachment && StoragePath::inDirectory($previous, Achievement::DIRECTORY)) {
            Storage::disk(Achievement::disk())->delete($previous);
        }

        return $achievement;
    }

    /**
     * قواعد الإجراء، والحقول في اللوحة بالقواعد نفسها (AchievementResource). والإنجاز لا يكون بتاريخ مستقبلي بتوقيت الرياض.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200', new PlainText],
            'achieved_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.self::MIN_DATE, 'before_or_equal:'.self::maxDate()],
            'details' => ['required', 'string', 'max:5000', new PlainText(multiline: true)],
            'status' => ['required', Rule::enum(AchievementStatus::class)],
            'attachment' => ['nullable', 'string', 'max:255'],
            'attachment_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function maxDate(): string
    {
        return now(config('app.display_timezone'))->toDateString();
    }

    /**
     * @return array{size: int, extension: string}
     *
     * @throws ValidationException
     */
    private static function check(string $path, ?string $previous): array
    {
        if (Achievement::withTrashed()->where('attachment', $path)->exists()) {
            throw ValidationException::withMessages(['attachment' => __('validation.file_in_use')]);
        }

        try {
            return PrivateUpload::check(Achievement::disk(), Achievement::DIRECTORY, $path, 'attachment', __('achievements.validation.invalid_file'));
        } catch (ValidationException $exception) {
            self::discard($path, $previous);

            throw $exception;
        }
    }

    /**
     * مرفوع رُفض قبل حفظه: يُحذف إن كان في مجلد الإنجازات ولا يستعمله إنجاز (ولو محذوف ناعماً).
     */
    private static function discard(?string $path, ?string $previous): void
    {
        if ($path !== null && $path !== $previous && StoragePath::inDirectory($path, Achievement::DIRECTORY)
            && ! Achievement::withTrashed()->where('attachment', $path)->exists()) {
            Storage::disk(Achievement::disk())->delete($path);
        }
    }
}
