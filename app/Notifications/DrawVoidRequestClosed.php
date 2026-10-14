<?php

namespace App\Notifications;

use App\Enums\DrawVoidRequestStatus;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Models\Competition;
use App\Models\CompetitionDrawVoidRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification as PanelNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * إبلاغ طالب إلغاء السحب بأن طلبه رُفض بسبب (RejectDrawVoid) أو سقط بمضي المدة (LapseDrawVoidRequests)،
 * فبقي السحب معتمداً: إشعار في اللوحة وبريد، في الطابور بعد اعتماد المعاملة.
 */
class DrawVoidRequestClosed extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public Competition $competition,
        public DrawVoidRequestStatus $status,
        public ?string $reason = null,
    ) {}

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
            ->action(__('competitions.draws.void_request_mail.action'), $this->url())
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
            ->icon('heroicon-o-no-symbol')
            ->status('warning')
            ->actions([Action::make('open')->label(__('competitions.draws.void_request_mail.action'))->url($this->url())])
            ->getDatabaseMessage();
    }

    private function title(): string
    {
        return __('competitions.draws.void_request_mail.'.$this->status->value.'_subject', ['competition' => $this->competition->title]);
    }

    private function body(): string
    {
        return __('competitions.draws.void_request_mail.'.$this->status->value.'_line', [
            'competition' => $this->competition->title,
            'reason' => (string) $this->reason,
            'days' => CompetitionDrawVoidRequest::LAPSE_DAYS,
        ]);
    }

    private function url(): string
    {
        return CompetitionResource::getUrl('view', ['record' => $this->competition], panel: 'admin');
    }
}
