<?php

namespace App\Notifications;

use App\Enums\RegisterAlertThreshold;
use App\Filament\Resources\RegisterEntries\RegisterEntryResource;
use App\Models\RegisterEntry;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Notifications\Notification as PanelNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه المسؤول بقرب انتهاء مستند أو بانتهائه (requirements/13 §1): إشعار في اللوحة وبريد، في الطابور
 * بعد اعتماد المعاملة. فيه عنوان المستند وتاريخ انتهائه ورابط صفحته في اللوحة، لا مرفقاته.
 */
class RegisterEntryAlert extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public RegisterEntry $entry, public RegisterAlertThreshold $threshold) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting(__('account.mail.greeting'))
            ->line($this->body())
            ->action(__('registers.mail.action'), $this->url())
            ->salutation(__('account.mail.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return PanelNotification::make()
            ->title($this->title())
            ->body($this->body())
            ->icon('heroicon-o-clock')
            ->status($this->threshold === RegisterAlertThreshold::Expired ? 'danger' : 'warning')
            ->actions([Action::make('open')->label(__('registers.mail.action'))->url($this->url())])
            ->getDatabaseMessage();
    }

    private function title(): string
    {
        return __('registers.mail.'.$this->threshold->value.'_subject', ['title' => $this->entry->title]);
    }

    private function body(): string
    {
        return __('registers.mail.'.$this->threshold->value.'_line', [
            'type' => $this->entry->type->getLabel(),
            'title' => $this->entry->title,
            'date' => Dates::display($this->entry->expires_on),
        ]);
    }

    private function url(): string
    {
        return RegisterEntryResource::getUrl('view', ['record' => $this->entry], panel: 'admin');
    }
}
