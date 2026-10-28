<?php

namespace App\Filament\Pages;

use App\Actions\Messaging\SendTestMessage;
use App\Actions\Messaging\UpdateMessagingChannels;
use App\Enums\MessageStatus;
use App\Enums\SmsProvider;
use App\Filament\Concerns\ReadableByOverseers;
use App\Models\MessageLog;
use App\Models\MessagingChannel;
use App\Models\User;
use App\Services\Messaging\MessagingSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * قنوات التواصل: مزود الرسائل النصية وواتساب ومفاتيحهما، وتفعيل كل استعمال، ورسالة اختبار، وآخر الرسائل
 * بحالاتها. للمشرف العام وحده (messaging.update لا تُربط بدور). والمفاتيح لا تُعرض أبداً: الحقل فارغ دائماً،
 * وتركه فارغاً يُبقي المحفوظ (docs/guides/messaging.md).
 *
 * @property-read Schema $form
 */
class MessagingChannels extends Page
{
    use ReadableByOverseers;

    protected static ?string $slug = 'messaging-channels';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 91;

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected static function canWrite(): bool
    {
        return auth()->user()?->can('messaging.update') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('messaging.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('messaging.title');
    }

    public function mount(): void
    {
        $this->fillForm();
    }

    private function fillForm(): void
    {
        $sms = MessagingChannel::for(MessagingChannel::SMS);
        $whatsapp = MessagingChannel::for(MessagingChannel::WHATSAPP);

        // المفاتيح لا تُملأ: الحقول فارغة دائماً
        $this->form->fill([
            'sms' => [
                'provider' => $sms->provider,
                'sender' => $sms->option('sender'),
                'username' => $sms->option('username'),
                'usages' => ['request_updates' => MessagingSettings::usageEnabled('sms', 'request_updates')],
            ],
            'whatsapp' => [
                'provider' => $whatsapp->provider,
                'phone_number_id' => $whatsapp->option('phone_number_id'),
                'graph_version' => $whatsapp->option('graph_version') ?? config('services.whatsapp.graph_version'),
                'language' => $whatsapp->option('language') ?? config('services.whatsapp.language'),
                'templates' => ['exam_receipt' => $whatsapp->option('templates.exam_receipt')],
                'usages' => ['exam_receipt' => MessagingSettings::usageEnabled('whatsapp', 'exam_receipt')],
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->disabled(fn (): bool => ! static::canWrite())
            ->components([
                Callout::make(__('messaging.not_ready.heading'))
                    ->description(fn (): string => __('messaging.not_ready.body', ['channels' => $this->unreadyChannels()]))
                    ->warning()
                    ->visible(fn (): bool => app()->isProduction() && $this->unreadyChannels() !== ''),

                Section::make(__('messaging.channels.sms'))
                    ->description(fn (): string => $this->statusLine(MessagingChannel::SMS))
                    ->columns(2)
                    ->schema([
                        Select::make('sms.provider')->label(__('messaging.fields.provider'))
                            ->options(SmsProvider::class)
                            ->placeholder(__('messaging.fields.from_env'))
                            ->helperText(__('messaging.fields.provider_hint')),
                        TextInput::make('sms.sender')->label(__('messaging.sms.sender'))
                            ->helperText(__('messaging.sms.sender_hint'))
                            ->maxLength(20)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('sms.username')->label(__('messaging.sms.username'))
                            ->maxLength(100)
                            ->helperText(__('messaging.sms.username_hint'))
                            ->extraInputAttributes(['dir' => 'ltr']),
                        ...$this->secretFields('sms', 'api_key', __('messaging.sms.api_key')),
                        Toggle::make('sms.usages.request_updates')->label(__('messaging.usages.request_updates'))
                            ->helperText(__('messaging.usages.hint'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('messaging.channels.whatsapp'))
                    ->description(fn (): string => $this->statusLine(MessagingChannel::WHATSAPP).' '.__('messaging.whatsapp.webhook_hint', ['url' => route('messaging.whatsapp.webhook')]))
                    ->columns(2)
                    ->schema([
                        Select::make('whatsapp.provider')->label(__('messaging.fields.provider'))
                            ->options(['cloud' => __('messaging.whatsapp.providers.cloud')])
                            ->placeholder(__('messaging.fields.from_env'))
                            ->helperText(__('messaging.fields.provider_hint')),
                        TextInput::make('whatsapp.phone_number_id')->label(__('messaging.whatsapp.phone_number_id'))
                            ->maxLength(30)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('whatsapp.graph_version')->label(__('messaging.whatsapp.graph_version'))
                            ->maxLength(10)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('whatsapp.language')->label(__('messaging.whatsapp.language'))
                            ->helperText(__('messaging.whatsapp.language_hint'))
                            ->maxLength(6)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('whatsapp.templates.exam_receipt')->label(__('messaging.whatsapp.templates.exam_receipt'))
                            ->helperText(__('messaging.whatsapp.templates.exam_receipt_hint'))
                            ->maxLength(512)
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->columnSpanFull(),
                        ...$this->secretFields('whatsapp', 'access_token', __('messaging.whatsapp.access_token')),
                        ...$this->secretFields('whatsapp', 'app_secret', __('messaging.whatsapp.app_secret')),
                        ...$this->secretFields('whatsapp', 'verify_token', __('messaging.whatsapp.verify_token')),
                        Toggle::make('whatsapp.usages.exam_receipt')->label(__('messaging.usages.exam_receipt'))
                            ->helperText(__('messaging.usages.hint'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * حقل المفتاح فارغاً دائماً (لا يُرسل المحفوظ إلى المتصفح)، ومعه «حذف المفتاح» إن كان محفوظاً.
     *
     * @return list<TextInput|Toggle>
     */
    private function secretFields(string $channel, string $name, string $label): array
    {
        $stored = fn (): bool => MessagingChannel::for($channel)->secret($name) !== null;

        return [
            TextInput::make($channel.'.'.$name)->label($label)
                ->password()
                ->autocomplete('new-password')
                ->maxLength(1000)
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
                ->helperText(fn (): string => $stored() ? __('messaging.fields.secret_stored') : __('messaging.fields.secret_empty'))
                ->extraInputAttributes(['dir' => 'ltr']),
            Toggle::make($channel.'.clear_'.$name)->label(__('messaging.fields.clear_secret', ['name' => $label]))
                ->visible($stored),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('site.save'))->submit('save')->keyBindings(['mod+s']),
                    ])->visible(fn (): bool => static::canWrite()),
                ]),
            Section::make(__('messaging.logs.title'))
                ->description(__('messaging.logs.hint', ['days' => (int) config('messaging.log_days')]))
                ->schema([
                    View::make('filament.messaging.recent-logs')->viewData(fn (): array => ['logs' => $this->recentLogs()]),
                ]),
        ]);
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->testAction(MessagingChannel::SMS),
            $this->testAction(MessagingChannel::WHATSAPP),
        ];
    }

    private function testAction(string $channel): Action
    {
        $fields = [
            TextInput::make('phone')->label(__('messaging.test.phone'))
                ->helperText(__('messaging.test.phone_hint'))
                ->required()
                ->maxLength(16)
                ->extraInputAttributes(['dir' => 'ltr']),
        ];

        if ($channel === MessagingChannel::WHATSAPP) {
            $fields[] = TextInput::make('template')->label(__('messaging.test.template'))
                ->helperText(__('messaging.test.template_hint'))
                ->required()
                ->maxLength(512)
                ->extraInputAttributes(['dir' => 'ltr']);
        }

        return Action::make('test_'.$channel)
            ->visible(fn (): bool => static::canWrite())
            ->label(__('messaging.test.action_'.$channel))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->modalSubmitActionLabel(__('messaging.test.send'))
            ->schema($fields)
            ->action(function (array $data, SendTestMessage $send) use ($channel): void {
                /** @var User $user */
                $user = auth()->user();

                try {
                    $log = $send->handle($user, $channel, $data);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();

                    return;
                }

                $notification = Notification::make()->title(__('messaging.test.result', ['status' => $log->status->getLabel()]));

                if ($log->status === MessageStatus::Sent) {
                    $notification->success()->body(__('messaging.test.sent_body', ['provider' => $log->provider]));
                } else {
                    $notification->danger()->body(__('messaging.test.failed_body', ['error' => $log->error ?? '-']));
                }

                $notification->send();
            });
    }

    public function save(UpdateMessagingChannels $action): void
    {
        $this->authorizeWrite();

        /** @var User $user */
        $user = auth()->user();

        // المفاتيح المكتوبة لا تبقى في حالة الصفحة بأي حال: بعد الحفظ، أو رفض حقول النموذج (getState)، أو
        // رفض الإجراء، أو أي خطأ آخر
        try {
            /** @var array{sms: array<string, mixed>, whatsapp: array<string, mixed>} $state */
            $state = $this->form->getState();

            $action->handle($user, $state);
        } catch (ValidationException $exception) {
            // أخطاء getState بمسارات النموذج (data.)، وأخطاء الإجراء بمسارات الإعدادات فتُنقل إليها
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key) => [(str_starts_with($key, 'data.') ? '' : 'data.').$key => $messages])->all(),
            );
        } finally {
            $this->forgetSecrets();
        }

        $this->fillForm();

        Notification::make()->success()->title(__('messaging.saved'))->send();
    }

    private function forgetSecrets(): void
    {
        foreach (UpdateMessagingChannels::SECRETS as $channel => $names) {
            foreach ($names as $name) {
                $this->data[$channel][$name] = null;
            }
        }
    }

    private function statusLine(string $channel): string
    {
        $settings = $channel === MessagingChannel::SMS ? MessagingSettings::sms() : MessagingSettings::whatsapp();

        return __('messaging.status.line', [
            'provider' => $settings['provider'],
            'source' => __('messaging.status.sources.'.$settings['source']),
            'state' => __(MessagingSettings::ready($channel) ? 'messaging.status.ready' : 'messaging.status.not_ready'),
        ]);
    }

    /**
     * القنوات التي يلزمها مزود ولا مزود لها: الرسائل النصية دائماً (رموز التحقق)، وواتساب متى فُعّل له استعمال.
     */
    private function unreadyChannels(): string
    {
        $needed = [MessagingChannel::SMS];

        if (collect(array_keys((array) config('messaging.usages.whatsapp')))->contains(fn (string $usage): bool => MessagingSettings::usageEnabled('whatsapp', $usage))) {
            $needed[] = MessagingChannel::WHATSAPP;
        }

        return collect($needed)
            ->reject(fn (string $channel): bool => MessagingSettings::ready($channel))
            ->map(fn (string $channel): string => __('messaging.channels.'.$channel))
            ->implode('، ');
    }

    /**
     * @return Collection<int, MessageLog>
     */
    private function recentLogs(): Collection
    {
        return MessageLog::query()->latest('id')->limit(20)->get();
    }
}
