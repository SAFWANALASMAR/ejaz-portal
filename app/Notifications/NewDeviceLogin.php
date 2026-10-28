<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * دخول إلى الحساب من متصفح جديد (NoticeNewDeviceLogin): الوقت ونوع المتصفح والنظام، بلا عنوان الشبكة.
 */
class NewDeviceLogin extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public Carbon $at, public string $userAgent) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('account.new_device_mail.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.new_device_mail.body', [
                'time' => $this->at->copy()->timezone((string) config('app.display_timezone'))->format('Y-m-d H:i'),
                'device' => $this->device(),
            ]))
            ->line(__('account.new_device_mail.not_you'))
            ->salutation(__('account.mail.salutation'));
    }

    /**
     * وصف تقريبي من نص المتصفح: الاسم والنظام فقط.
     */
    private function device(): string
    {
        $agent = $this->userAgent;

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        $system = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        if ($browser === null && $system === null) {
            return __('account.new_device_mail.unknown');
        }

        return trim(($browser ?? '').' — '.($system ?? ''), ' —');
    }
}
