<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Payments\RefundPayment;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * صفحة العملية، وفيها إجراء الاسترداد لمن له payments.refund بسبب إلزامي (RefundPayment).
 */
class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refund')
                ->label(__('payments.refund.action'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Paid && (auth()->user()?->can('refund', $record) ?? false))
                ->modalDescription(__('payments.refund.confirm'))
                ->schema([
                    Textarea::make('reason')->label(__('payments.refund.reason'))->helperText(__('payments.refund.reason_hint'))->required()->minLength(10)->maxLength(500),
                    // عملية مالية لا رجعة فيها: إعادة توثيق بكلمة المرور، لا الجلسة المفتوحة وحدها
                    TextInput::make('password')->label(__('payments.refund.password'))->password()->required()->currentPassword()->autocomplete('current-password'),
                ])
                ->action(function (Payment $record, array $data): void {
                    /** @var User $user */
                    $user = auth()->user();

                    try {
                        app(RefundPayment::class)->handle($user, $record, (string) $data['reason']);
                        Notification::make()->success()->title(__('payments.refund.done'))->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->danger()->title(__('payments.refund.failed'))->send();
                    }

                    $this->refreshFormData(['status', 'refunded_at', 'refund_reason']);
                }),
        ];
    }
}
