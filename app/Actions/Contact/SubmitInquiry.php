<?php

namespace App\Actions\Contact;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\InquiryConfirmation;
use App\Notifications\InquiryReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * حفظ رسالة «الاستفسارات والاقتراحات» طلباً برقم مرجعي وحالة «مُستلم»، ثم إشعار الجمعية على
 * بريد التواصل في الإعدادات، وتأكيد للمرسل برقمه وحده. والإشعاران في الطابور بعد اعتماد المعاملة.
 */
class SubmitInquiry
{
    /**
     * @param  array<string, mixed>  $data  الاسم، والبريد، والجوال، والنوع، والموضوع، والرسالة (ContactRequest)
     */
    public function handle(array $data, ?User $sender = null): Inquiry
    {
        $inquiry = DB::transaction(function () use ($data, $sender): Inquiry {
            $inquiry = new Inquiry([
                ...array_intersect_key($data, array_flip(['name', 'email', 'phone', 'type', 'subject', 'message'])),
                'user_id' => $sender?->id,
                'status' => InquiryStatus::Received,
            ]);
            $inquiry->reference = Inquiry::newReference();
            $inquiry->save();

            return $inquiry;
        });

        // بريد الجمعية من الإعدادات، فإن لم يُضبط بقي الطلب في اللوحة وحدها
        $email = Setting::group('contact')['email'] ?? null;

        if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            Notification::route('mail', $email)->notify(new InquiryReceived($inquiry));
        }

        Notification::route('mail', $inquiry->email)->notify(new InquiryConfirmation($inquiry->reference));

        return $inquiry;
    }
}
