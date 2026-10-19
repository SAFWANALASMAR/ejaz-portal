<?php

namespace App\Notifications\Channels;

use App\Actions\Messaging\DeliverMessage;
use App\Contracts\SmsGateway;
use App\Models\MessagingChannel;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * قناة الرسالة النصية للإشعارات: ترسل نص toSms() إلى جوال صاحب الحساب الموثق عبر مزود الرسائل،
 * وتسجّل حالتها. الإشعار نفسه في الطابور، فلا مهمة ثانية.
 */
class SmsChannel
{
    public function __construct(private SmsGateway $sms, private DeliverMessage $deliver) {}

    public function send(User $notifiable, Notification $notification): void
    {
        if ($notifiable->phone === null || ! $notifiable->hasVerifiedPhone() || ! method_exists($notification, 'toSms')) {
            return;
        }

        $phone = $notifiable->phone;
        $message = (string) $notification->toSms($notifiable);

        $this->deliver->handle(MessagingChannel::SMS, $phone, 'notification', fn () => $this->sms->send($phone, $message));
    }
}
