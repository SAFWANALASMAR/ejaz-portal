<?php

namespace App\Actions\Settings;

use App\Enums\ActivityEvent;
use App\Jobs\SyncOnlineMeeting;
use App\Models\Meeting;
use App\Models\Setting;
use App\Models\User;
use App\Support\TeamsConnection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * حفظ إعداد ربط تيمز (للمشرف العام وحده — online_meetings.update): التفعيل، والمستأجر، ومعرّف التطبيق،
 * وبريد المنظِّم، والسر مشفّراً. والسر الفارغ يُبقي المحفوظ، فلا يُعاد إلى الشاشة ليُحفظ ثانية. والتعديل
 * يُسجَّل بلا السر: هل تغيّر فقط. وحفظه مفعّلاً يعيد مزامنة كل اجتماع تعذّر ربطه.
 */
class UpdateOnlineMeetingSettings
{
    // معرّف GUID، أو نطاق المستأجر (ejaz.onmicrosoft.com)
    private const TENANT = '/^([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|[a-z0-9-]+(\.[a-z0-9-]+)+)$/i';

    /**
     * @param  array<string, mixed>  $data
     * @return int الاجتماعات المتعذرة التي أُعيدت مزامنتها بعد التفعيل
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, #[SensitiveParameter] array $data): int
    {
        if (! $actor->can('online_meetings.update')) {
            throw new AuthorizationException;
        }

        $stored = Setting::group(TeamsConnection::SETTING);
        $enabled = filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $data = array_map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $data);

        $validated = Validator::make($data, [
            'enabled' => ['boolean'],
            'tenant_id' => [Rule::requiredIf($enabled), 'nullable', 'string', 'max:255', 'regex:'.self::TENANT],
            'client_id' => [Rule::requiredIf($enabled), 'nullable', 'uuid'],
            'organizer' => [Rule::requiredIf($enabled), 'nullable', 'email:rfc', 'max:254'],
            'client_secret' => [Rule::requiredIf($enabled && blank($stored['client_secret'] ?? null)), 'nullable', 'string', 'max:255'],
        ], attributes: __('settings.online_meetings.attributes'))->validate();

        $secretChanged = filled($validated['client_secret'] ?? null);

        Setting::put(TeamsConnection::SETTING, [
            'enabled' => $enabled,
            'tenant_id' => $validated['tenant_id'] ?? null,
            'client_id' => isset($validated['client_id']) ? strtolower($validated['client_id']) : null,
            'organizer' => $validated['organizer'] ?? null,
            'client_secret' => $secretChanged ? Crypt::encryptString($validated['client_secret']) : ($stored['client_secret'] ?? null),
        ]);

        activity('security')->causedBy($actor)
            ->event(ActivityEvent::OnlineMeetingsSettingsUpdated->value)
            ->withProperties([
                'enabled' => $enabled,
                'tenant_id' => $validated['tenant_id'] ?? null,
                'client_id' => $validated['client_id'] ?? null,
                'organizer' => $validated['organizer'] ?? null,
                'secret_changed' => $secretChanged,
            ])
            ->log(ActivityEvent::OnlineMeetingsSettingsUpdated->value);

        if (! $enabled) {
            return 0;
        }

        // الربط المفعّل يعيد ما تعذّر من قبل (ومنه «غير مضبوط»)، فلا يبقى رابط اجتماع ملغى ينتظر يداً
        $failed = Meeting::withTrashed()->whereNotNull('online_sync_error')->pluck('id');
        $failed->each(fn (int $id) => SyncOnlineMeeting::dispatch($id));

        return $failed->count();
    }
}
