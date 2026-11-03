<?php

namespace App\Actions\Assembly;

use App\Enums\ActivityEvent;
use App\Enums\MeetingBody;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Jobs\SyncOnlineMeeting;
use App\Models\AssemblyMembership;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingNotice;
use App\Rules\MeetingJoinUrl;
use App\Support\PrivatePdf;
use App\Support\StoragePath;
use App\Support\TeamsConnection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * جدولة اجتماع للجمعية العمومية أو تعديله (السكرتير، والمجدول وحده يُعدَّل — MeetingPolicy).
 *
 * - المكان أو تيمز أو كلاهما، والاجتماع عن بعد بموعد انتهاء. والمرفقات PDF حقيقية في مجلدها.
 * - رابط الاجتماع اليدوي (تيمز أو زووم أو ميت): يلزم الاجتماع عن بعد إن كان ربط تيمز مطفأً، ومتى لُصق غلب
 *   الربط فلا يُنشأ اجتماع تيمز، ويُلغى ما كان (docs/decisions/2026-11-03-meeting-manual-link.md).
 * - بعد الحفظ: مزامنة تيمز في الطابور متى لزمت، ودعوة الأعضاء الفعّالين عند الجدولة، وإبلاغهم إن تغيّر
 *   الموعد أو المكان. وكل ذلك لاجتماع لم يحن موعده بعد.
 */
class SaveMeeting
{
    private const MAX_ATTACHMENTS = 10;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Meeting $meeting, array $data): Meeting
    {
        $creating = ! $meeting->exists;

        if (! ($creating ? $actor->can('create', Meeting::class) : $actor->can('update', $meeting))) {
            throw new AuthorizationException;
        }

        // الجهة (requirements/13 §2): بلا قيمة تبقى جهة الاجتماع، والجديد اجتماعُ جمعية عمومية كما كان
        $data['body'] ??= $meeting->body->value;
        $data['body'] = $data['body'] instanceof MeetingBody ? $data['body']->value : $data['body'];
        $committee = $data['body'] === MeetingBody::Committee->value;

        // الجهة لا تتغير بعد الإنشاء: دعواتها أُرسلت لأعضائها، وحضورها ونصابها منهم
        if (! $creating) {
            $data['committee_id'] = isset($data['committee_id']) ? (int) $data['committee_id'] : $meeting->committee_id;

            if ($data['body'] !== $meeting->body->value || $data['committee_id'] !== $meeting->committee_id) {
                throw ValidationException::withMessages(['body' => __('registers.validation.body_locked')]);
            }
        }

        $online = filter_var($data['is_online'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $manualRequired = self::manualLinkRequired($meeting, $online);
        // الرابط المنسوخ قد يحمل مسافة أو سطراً في طرفيه
        if (is_string($data['manual_join_url'] ?? null)) {
            $data['manual_join_url'] = trim($data['manual_join_url']);
        }

        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::enum(MeetingType::class)],
            'body' => ['required', Rule::enum(MeetingBody::class)],
            'committee_id' => [
                Rule::requiredIf($committee),
                Rule::prohibitedIf(! $committee),
                'nullable', 'integer', Rule::exists('committees', 'id')->whereNull('deleted_at'),
            ],
            'starts_at' => ['required', 'date'],
            'ends_at' => [Rule::requiredIf($online), 'nullable', 'date', 'after:starts_at'],
            'location' => [Rule::requiredIf(! $online), 'nullable', 'string', 'max:300'],
            'is_online' => ['boolean'],
            'manual_join_url' => [Rule::excludeIf(! $online), Rule::requiredIf($manualRequired), 'nullable', 'string', new MeetingJoinUrl],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'quorum_percent' => ['nullable', 'integer', 'between:1,100'],
            'attachments' => ['array', 'max:'.self::MAX_ATTACHMENTS],
            'attachments.*.path' => ['required', 'string', 'max:255'],
            'attachments.*.name' => ['required', 'string', 'max:200'],
        ], [
            'location.required' => __('assembly.validation.place_or_online'),
            'manual_join_url.required' => __('assembly.validation.manual_link_required'),
        ])->validate();

        // تعذر تيمز (ومنه الربط المطفأ) لا يمنع الحفظ: سببه على الاجتماع للمسؤول مع إعادة المحاولة (SyncOnlineMeeting)

        $before = collect($meeting->attachments ?? [])->keyBy('path');
        $attachments = array_map(fn (array $file): array => $before->get($file['path']) ?? self::newAttachment($meeting, $file), $validated['attachments'] ?? []);

        $manualUrl = $online && filled($validated['manual_join_url'] ?? null) ? (string) $validated['manual_join_url'] : null;
        $previousManual = $meeting->online_join_manual ? $meeting->online_join_url : null;

        DB::transaction(function () use ($actor, $meeting, $validated, $online, $attachments, $creating, $manualUrl, $previousManual): void {
            $meeting->fill([
                'title' => $validated['title'],
                'type' => $validated['type'],
                'body' => $validated['body'],
                'committee_id' => $validated['committee_id'] ?? null,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'] ?? null,
                'location' => $validated['location'] ?? null,
                'is_online' => $online,
                'agenda' => $validated['agenda'] ?? null,
                'quorum_percent' => $validated['quorum_percent'] ?? null,
                'attachments' => $attachments === [] ? null : array_values($attachments),
            ]);

            if ($creating) {
                $meeting->status = MeetingStatus::Scheduled;
                $meeting->created_by = $actor->id;
            }

            // الرابط اليدوي في عمود الرابط المشفّر نفسه، فيظهر حيث يظهر رابط تيمز. وإزالته ترد الاجتماع إلى الربط
            if ($manualUrl !== null) {
                $meeting->forceFill(['online_join_url' => $manualUrl, 'online_join_manual' => true, 'online_sync_error' => null, 'online_sync_failed_at' => null]);
            } elseif ($meeting->online_join_manual) {
                $meeting->forceFill(['online_join_url' => null, 'online_join_manual' => false]);
            }

            $meeting->save();

            // في السجل نطاق الرابط وحده، فالرابط نفسه سر لا يدخله (Meeting::getActivitylogOptions)
            if ($manualUrl !== $previousManual) {
                activity('assembly')->causedBy($actor)->performedOn($meeting)
                    ->event(ActivityEvent::MeetingJoinLinkChanged->value)
                    ->withProperties(['host' => $manualUrl === null ? null : parse_url($manualUrl, PHP_URL_HOST), 'removed' => $manualUrl === null])
                    ->log(ActivityEvent::MeetingJoinLinkChanged->value);
            }
        });

        // المرفق المحذوف من الاجتماع يُحذف ملفه بعد اعتماد الحفظ
        $kept = array_column($attachments, 'path');
        $removed = $before->keys()->reject(fn (string $path): bool => in_array($path, $kept, true) || ! StoragePath::inDirectory($path, Meeting::ATTACHMENTS_DIRECTORY));
        Storage::disk(Meeting::disk())->delete($removed->values()->all());

        if (($meeting->wantsOnlineMeeting() || $meeting->hasOnlineMeeting()) && ($creating || $meeting->wasChanged(['title', 'starts_at', 'ends_at', 'is_online', 'online_join_manual']))) {
            SyncOnlineMeeting::dispatch($meeting->id);
        }

        if ($meeting->starts_at->isFuture()) {
            if ($creating) {
                self::notifyMembers(new MeetingNotice($meeting, 'scheduled'));
            } elseif ($meeting->wasChanged(['starts_at', 'ends_at', 'location', 'is_online'])) {
                self::notifyMembers(new MeetingNotice($meeting, 'updated'));
            }
        }

        return $meeting;
    }

    /**
     * يلزم الرابط اليدوي للاجتماع عن بعد بلا ربط آلي، إلا اجتماعاً له في تيمز اجتماع قائم برابطه. ويستعمله
     * نموذج اللوحة (MeetingForm) فلا يختلف الشرطان.
     */
    public static function manualLinkRequired(?Meeting $meeting, bool $online): bool
    {
        return $online && ! TeamsConnection::isOn() && ! ($meeting?->hasOnlineMeeting() ?? false);
    }

    /**
     * دعوة الأعضاء الفعّالين أو إبلاغهم، على دفعات، وكل إشعار في الطابور بعد اعتماد المعاملة.
     */
    public static function notifyMembers(MeetingNotice $notice): void
    {
        // الدعوات لاجتماع الجمعية العمومية وحده: رابطها منطقة الأعضاء، ولا يُدعى أعضاؤها لاجتماع المجلس أو اللجان
        if ($notice->meeting->body !== MeetingBody::GeneralAssembly) {
            return;
        }

        AssemblyMembership::activeUsers()->chunkById(200, fn ($users) => Notification::send($users, $notice));
    }

    /**
     * @param  array{path: string, name: string}  $file
     * @return array{path: string, name: string, size: int}
     *
     * @throws ValidationException
     */
    private static function newAttachment(Meeting $meeting, array $file): array
    {
        // ملف اجتماع آخر (ولو محذوف ناعماً) لا يُعاد استعماله، فلا يحذفه حذفُه منه
        $inUse = Meeting::withTrashed()
            ->whereJsonContains('attachments', [['path' => $file['path']]])
            ->when($meeting->exists, fn ($query) => $query->whereKeyNot($meeting->getKey()))
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['attachments' => __('validation.file_in_use')]);
        }

        return [
            'path' => $file['path'],
            // الاسم في ترويسة التنزيل، ولا تقبل ترويسة التنزيل الشرطتين المائلتين
            'name' => str_replace(['/', '\\'], '-', $file['name']),
            'size' => PrivatePdf::check(Meeting::disk(), Meeting::ATTACHMENTS_DIRECTORY, $file['path'], 'attachments'),
        ];
    }
}
