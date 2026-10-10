<?php

namespace App\Notifications\Channels;

use App\Contracts\SmsGateway;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * قناة الرسالة النصية للإشعارات: ترسل نص toSms() إلى جوال صاحب الحساب الموثق عبر مزود الرسائل.
 * الإشعار نفسه في الطابور، فلا مهمة ثانية.
 */
class SmsChannel
{
    public function __construct(private SmsGateway $sms) {}

    public function send(User $notifiable, Notification $notification): void
    {
        if ($notifiable->phone === null || ! $notifiable->hasVerifiedPhone() || ! method_exists($notification, 'toSms')) {
            return;
        }

        $this->sms->send($notifiable->phone, (string) $notification->toSms($notifiable));
    }
}
