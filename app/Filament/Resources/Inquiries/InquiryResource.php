<?php

namespace App\Filament\Resources\Inquiries;

use App\Actions\Contact\UpdateInquiry;
use App\Enums\InquiryStatus;
use App\Enums\InquiryType;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\Inquiries\Pages\ListInquiries;
use App\Filament\Resources\Inquiries\Pages\ViewInquiry;
use App\Models\Inquiry;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * رسائل «الاستفسارات والاقتراحات». الصلاحيات: requests.* — InquiryPolicy (السكرتير). تصل من
 * الموقع، فلا إنشاء ولا تعديل لنصها هنا، بل معالجة: الحالة، والإسناد، والملاحظة (UpdateInquiry).
 */
class InquiryResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'inquiries';

    protected static string $navigationGroupKey = 'requests';

    protected static ?string $model = Inquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->label(__('inquiries.fields.reference'))->searchable()->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('type')->label(__('inquiries.fields.type'))->badge(),
                TextColumn::make('subject')->label(__('inquiries.fields.subject'))->searchable()->wrap()->limit(60),
                TextColumn::make('name')->label(__('inquiries.fields.name'))->searchable(),
                TextColumn::make('status')->label(__('inquiries.fields.status'))->badge(),
                TextColumn::make('assignee.name')->label(__('inquiries.fields.assignee'))->placeholder(__('inquiries.unassigned')),
                TextColumn::make('created_at')->label(__('inquiries.fields.created_at'))->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('inquiries.fields.status'))->options(InquiryStatus::class),
                SelectFilter::make('type')->label(__('inquiries.fields.type'))->options(InquiryType::class),
                SelectFilter::make('assigned_to')->label(__('inquiries.fields.assignee'))->options(fn (): array => UpdateInquiry::assignees()),
                TrashedFilter::make(),
            ])
            // حذف ناعم فردي واستعادة، ولا حذف نهائي (InquiryPolicy)
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $multiline = ['style' => 'white-space: pre-line'];

        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('reference')->label(__('inquiries.fields.reference')),
                TextEntry::make('type')->label(__('inquiries.fields.type'))->badge(),
                TextEntry::make('status')->label(__('inquiries.fields.status'))->badge(),
                TextEntry::make('created_at')->label(__('inquiries.fields.created_at'))->dateTime('Y-m-d H:i'),
                TextEntry::make('assignee.name')->label(__('inquiries.fields.assignee'))->placeholder(__('inquiries.unassigned')),
                TextEntry::make('user.name')->label(__('inquiries.fields.user'))->placeholder(__('inquiries.guest')),
            ]),
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('name')->label(__('inquiries.fields.name')),
                TextEntry::make('email')->label(__('inquiries.fields.email'))->copyable(),
                TextEntry::make('phone')->label(__('inquiries.fields.phone'))->placeholder('—'),
            ]),
            Section::make()->columnSpanFull()->schema([
                TextEntry::make('subject')->label(__('inquiries.fields.subject')),
                TextEntry::make('message')->label(__('inquiries.fields.message'))->extraAttributes($multiline),
                TextEntry::make('internal_note')->label(__('inquiries.fields.internal_note'))->placeholder('—')->extraAttributes($multiline),
            ]),
        ]);
    }

    /**
     * المحذوفة حذفاً ناعماً تظهر بمرشّحها، لتُستعاد.
     *
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInquiries::route('/'),
            'view' => ViewInquiry::route('/{record}'),
        ];
    }
}
