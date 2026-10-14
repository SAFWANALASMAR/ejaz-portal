<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use Database\Factories\InquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * رسالة «الاستفسارات والاقتراحات»: من زائر أو صاحب حساب، برقم مرجعي عام وحالة ومسؤول.
 * بياناتها الشخصية لمن له requests.view وحده، ولا تدخل سجل العمليات.
 *
 * @property InquiryType $type
 * @property InquiryStatus $status
 */
#[Fillable(['user_id', 'name', 'email', 'phone', 'type', 'subject', 'message', 'status', 'assigned_to', 'internal_note'])]
class Inquiry extends Model
{
    /** @use HasFactory<InquiryFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * النص الحر، يُحذف عند إخفاء هوية المرسل (قرار صفوان، 14 أكتوبر 2026)، فيبقى للإحصاء الرقم والنوع
     * والحالة والتاريخ. والملاحظة الداخلية معه، لأنها قد تنقل من نصه.
     */
    public const FREE_TEXT = ['subject', 'message', 'internal_note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InquiryType::class,
            'status' => InquiryStatus::class,
        ];
    }

    /**
     * المعرّف الرقمي للعلاقات وروابط اللوحة. وuuid محجوز لرابط عام إن احتيج (صفحة متابعة الرسالة)،
     * فلا يظهر الرقم التسلسلي في رابط عام (docs/architecture.md).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * رقم مرجعي يُعطى للمرسل، لا يتكرر في القاعدة.
     */
    public static function newReference(): string
    {
        do {
            $reference = self::randomReference();
        } while (self::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * رقم بشكل المرجعي بلا استعلام: حروف وأرقام لا تلتبس (بلا 0 وO و1 وI)، فلا يُخمَّن ولا يُخطأ
     * نقله. ويكفي وحده للرد الوهمي على الروبوت، فلا يكلّف القاعدة شيئاً.
     */
    public static function randomReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $reference = 'INQ-';

        for ($i = 0; $i < 8; $i++) {
            $reference .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $reference;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * السجل للمعالجة وحدها (الحالة، والمسؤول)، لا للبيانات الشخصية. وتعديل الملاحظة الداخلية حدث
     * بلا قيمتها (UpdateInquiry)، لأنها قد تحوي بيانات شخصية يكتبها الموظف.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'assigned_to'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
