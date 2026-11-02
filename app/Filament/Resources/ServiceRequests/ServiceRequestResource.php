<?php

namespace App\Filament\Resources\ServiceRequests;

use App\Enums\FormFieldType;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Filament\Resources\ServiceRequests\Tables\ServiceRequestsTable;
use App\Models\ServiceRequest;
use App\Support\SaudiPhone;
use BackedEnum;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * الطلبات الإلكترونية المرسلة: التصفية والإسناد وتغيير الحالة والتصدير. الصلاحيات: requests.* —
 * ServiceRequestPolicy. والمسودات لا تظهر هنا؛ هي لأصحابها.
 */
class ServiceRequestResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'services.admin';

    protected static string $navigationGroupKey = 'requests';

    protected static ?string $model = ServiceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    /**
     * @return Builder<ServiceRequest>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ServiceRequest> $query */
        $query = parent::getEloquentQuery();

        // طلب الموظف نفسه لا يظهر له في اللوحة ولا في تصديرها، فلا يرى ملاحظات زملائه عليه
        return $query->submitted()->where('user_id', '!=', auth()->id())->with(['opening', 'user', 'assignee']);
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function table(Table $table): Table
    {
        return ServiceRequestsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('reference')->label(__('services.admin.fields.reference'))->copyable(),
                TextEntry::make('status')->label(__('services.admin.fields.status'))->badge(),
                TextEntry::make('assignee.name')->label(__('services.admin.fields.assignee'))->placeholder('—'),
                TextEntry::make('opening.title')->label(__('services.admin.fields.opening')),
                TextEntry::make('opening.kind')->label(__('services.admin.fields.kind')),
                TextEntry::make('submitted_at')->label(__('services.admin.fields.submitted_at'))->dateTime('Y-m-d H:i'),
                TextEntry::make('user.name')->label(__('services.admin.fields.applicant')),
                TextEntry::make('user.email')->label(__('services.admin.fields.email')),
                TextEntry::make('user.phone')->label(__('services.admin.fields.phone'))->formatStateUsing(fn (?string $state): string => SaudiPhone::display($state)),
            ]),
            Section::make(__('services.admin.fields.answers'))->columnSpanFull()->schema(fn (?ServiceRequest $record): array => self::answerEntries($record)),
            Section::make(__('services.admin.fields.history'))->columnSpanFull()->schema([
                RepeatableEntry::make('events')
                    ->label(__('services.admin.fields.history'))->hiddenLabel()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('created_at')->label(__('activity.fields.created_at'))->dateTime('Y-m-d H:i'),
                        TextEntry::make('to_status')->label(__('services.admin.fields.status'))->badge(),
                        TextEntry::make('actor.name')->label(__('services.admin.fields.actor'))->placeholder('—'),
                        TextEntry::make('message')->label(__('services.admin.fields.message'))->placeholder('—'),
                        TextEntry::make('internal_note')->label(__('services.admin.fields.internal_note'))->placeholder('—')->columnSpanFull(),
                    ]),
            ]),
        ]);
    }

    /**
     * حقول الفرصة بإجاباتها. والمرفق يُنزَّل من إجراء الصفحة.
     *
     * @return list<TextEntry>
     */
    private static function answerEntries(?ServiceRequest $record): array
    {
        if ($record === null) {
            return [];
        }

        return array_map(fn (array $field): TextEntry => TextEntry::make('answer_'.$field['key'])
            ->label($field['label'])
            ->state($field['type'] === FormFieldType::Pdf ? ($record->files[$field['key']]['name'] ?? null) : ($record->answers[$field['key']] ?? null))
            ->placeholder('—'), $record->opening->fields());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceRequests::route('/'),
            'view' => ViewServiceRequest::route('/{record}'),
        ];
    }
}
