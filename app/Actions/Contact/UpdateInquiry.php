<?php

namespace App\Actions\Contact;

use App\Enums\ActivityEvent;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\User;
use App\Rules\PlainText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * معالجة الاستفسار في اللوحة: الحالة والملاحظة الداخلية بصلاحية requests.update، والإسناد بصلاحية
 * requests.assign إلى موظف نشط له الاطلاع على الطلبات. والحالة والمسؤول في سجل العمليات
 * (LogsActivity)، وتعديل الملاحظة حدث بلا قيمتها.
 */
class UpdateInquiry
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Inquiry $inquiry, array $data): Inquiry
    {
        if (! $actor->can('update', $inquiry)) {
            throw new AuthorizationException;
        }

        $data = Arr::only($data, ['status', 'assigned_to', 'internal_note']);

        // الحدود هنا لا في النموذج وحده، فتسري على كل مستدعٍ
        Validator::make($data, [
            'status' => ['sometimes', Rule::enum(InquiryStatus::class)],
            'assigned_to' => ['nullable', 'integer'],
            'internal_note' => ['nullable', 'string', 'max:2000', new PlainText(multiline: true)],
        ], [], [
            'status' => __('inquiries.fields.status'),
            'assigned_to' => __('inquiries.fields.assignee'),
            'internal_note' => __('inquiries.fields.internal_note'),
        ])->validate();

        $inquiry->fill($data);

        // نص الرسالة حُذف مع إخفاء هوية المرسل، فلا تُضاف ملاحظة قد تنقل منه (Inquiry::FREE_TEXT)
        if ($inquiry->textRemoved() && $inquiry->isDirty('internal_note') && filled($inquiry->internal_note)) {
            throw ValidationException::withMessages(['internal_note' => __('inquiries.validation.note_text_removed')]);
        }

        if ($inquiry->isDirty('assigned_to')) {
            if (! $actor->can('assign', $inquiry)) {
                throw new AuthorizationException;
            }

            if ($inquiry->assigned_to !== null && ! array_key_exists((int) $inquiry->assigned_to, self::assignees())) {
                throw ValidationException::withMessages(['assigned_to' => __('inquiries.validation.assignee')]);
            }
        }

        $noteChanged = $inquiry->isDirty('internal_note');

        DB::transaction(function () use ($actor, $inquiry, $noteChanged): void {
            $inquiry->save();

            if ($noteChanged) {
                activity('requests')
                    ->causedBy($actor)
                    ->performedOn($inquiry)
                    ->event(ActivityEvent::InquiryNoteUpdated->value)
                    ->log(ActivityEvent::InquiryNoteUpdated->value);
            }
        });

        return $inquiry;
    }

    /**
     * الموظفون النشطون الذين يُسند إليهم: من له الاطلاع على الطلبات بدوره (والمسؤول التقني بكل الصلاحيات). والمشرف العام اطلاع فقط فلا يُسند إليه.
     *
     * @return array<int, string>
     */
    public static function assignees(): array
    {
        return User::query()
            ->where('is_active', true)
            ->permission('requests.view')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
