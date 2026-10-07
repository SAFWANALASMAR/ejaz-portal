<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Payment;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * المدفوعات للقراءة: كل عملية بحالتها ومبلغها وإيصالها وأحداثها الثابتة. الصلاحيات payments.* —
 * PaymentPolicy: لا إنشاء ولا تعديل ولا حذف لأحد، والاسترداد في صفحة العملية لمن له payments.refund.
 */
class PaymentResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'payments';

    protected static string $navigationGroupKey = 'payments';

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('payments.fields.created_at'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('receipt_number')->label(__('payments.fields.receipt_number'))->searchable()->placeholder('—'),
                TextColumn::make('purpose')->label(__('payments.fields.purpose'))->badge(),
                TextColumn::make('amount')->label(__('payments.fields.amount'))
                    ->formatStateUsing(fn (Payment $record): string => $record->formattedAmount().' '.__('payments.currencies.'.$record->currency)),
                TextColumn::make('status')->label(__('payments.fields.status'))->badge(),
                TextColumn::make('donor_name')->label(__('payments.fields.donor'))->placeholder('—')->searchable()->visible(self::canViewDonor()),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('payments.fields.status'))->options(PaymentStatus::class),
                SelectFilter::make('purpose')->label(__('payments.fields.purpose'))->options(PaymentPurpose::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextEntry::make('status')->label(__('payments.fields.status'))->badge(),
                TextEntry::make('purpose')->label(__('payments.fields.purpose'))->badge(),
                TextEntry::make('amount')->label(__('payments.fields.amount'))
                    ->formatStateUsing(fn (Payment $record): string => $record->formattedAmount().' '.__('payments.currencies.'.$record->currency)),
                TextEntry::make('receipt_number')->label(__('payments.fields.receipt_number'))->placeholder('—'),
                TextEntry::make('created_at')->label(__('payments.fields.created_at'))->dateTime('Y-m-d H:i'),
                TextEntry::make('paid_at')->label(__('payments.fields.paid_at'))->dateTime('Y-m-d H:i')->placeholder('—'),
                // هوية المتبرع بصلاحية أدق من الاطلاع على السجل المالي (payments.view_donor)
                TextEntry::make('donor_name')->label(__('payments.fields.donor'))->placeholder('—')->visible(self::canViewDonor()),
                TextEntry::make('donor_email')->label(__('payments.fields.email'))->placeholder('—')->visible(self::canViewDonor()),
                TextEntry::make('user.name')->label(__('payments.fields.user'))->placeholder('—')->visible(self::canViewDonor()),
                TextEntry::make('gateway')->label(__('payments.fields.gateway')),
                TextEntry::make('gateway_reference')->label(__('payments.fields.gateway_reference'))->placeholder('—')->copyable(),
                TextEntry::make('gateway_message')->label(__('payments.fields.gateway_message'))->placeholder('—'),
                TextEntry::make('refunded_at')->label(__('payments.fields.refunded_at'))->dateTime('Y-m-d H:i')->placeholder('—'),
                TextEntry::make('refundedBy.name')->label(__('payments.fields.refunded_by'))->placeholder('—'),
                TextEntry::make('refund_reason')->label(__('payments.fields.refund_reason'))->placeholder('—')->columnSpanFull(),
            ]),
            Section::make(__('payments.fields.events'))->schema([
                RepeatableEntry::make('events')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('type')->label(__('payments.fields.event_type'))->badge(),
                    TextEntry::make('created_at')->label(__('payments.fields.event_time'))->dateTime('Y-m-d H:i:s'),
                    TextEntry::make('gateway_event_id')->label(__('payments.fields.event_id'))->placeholder('—'),
                    TextEntry::make('payload.message')->label(__('payments.fields.message'))->placeholder('—'),
                ]),
            ]),
        ]);
    }

    public static function canViewDonor(): bool
    {
        return auth()->user()?->can('payments.view_donor') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
