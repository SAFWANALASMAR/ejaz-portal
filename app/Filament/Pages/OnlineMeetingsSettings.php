<?php

namespace App\Filament\Pages;

use App\Actions\Settings\TestOnlineMeetingsConnection;
use App\Actions\Settings\UpdateOnlineMeetingSettings;
use App\Filament\Concerns\ReadableByOverseers;
use App\Models\Meeting;
use App\Models\Setting;
use App\Models\User;
use App\Support\TeamsConnection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * ربط تيمز بمفتاح تطبيق الجمعية في Microsoft 365 (docs/guides/teams-meetings.md)، للمشرف العام وحده
 * (online_meetings.update). مطفأ حتى يُفعَّل هنا. والسر لا يُعرض ولا يعود إلى المتصفح: الحقل فارغ دائماً،
 * ويُمسح من حالة الصفحة في الطلب نفسه الذي أُرسل فيه، والفارغ يُبقي المحفوظ.
 *
 * @property-read Schema $form
 */
class OnlineMeetingsSettings extends Page
{
    use ReadableByOverseers;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static ?int $navigationSort = 91;

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected static function canWrite(): bool
    {
        return auth()->user()?->can('online_meetings.update') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('settings.online_meetings.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('settings.online_meetings.title');
    }

    public function mount(): void
    {
        $stored = Setting::group(TeamsConnection::SETTING);

        $this->form->fill([
            'enabled' => (bool) ($stored['enabled'] ?? false),
            'tenant_id' => $stored['tenant_id'] ?? null,
            'client_id' => $stored['client_id'] ?? null,
            'organizer' => $stored['organizer'] ?? null,
            'client_secret' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->disabled(fn (): bool => ! static::canWrite())
            ->components([
                // بغير المشغّل graph لا يُستعمل هذا الإعداد أصلاً (fake للتطوير، ويُطفأ في الإنتاج)
                Section::make(__('settings.online_meetings.driver_warning'))
                    ->description(__('settings.online_meetings.driver_warning_hint', ['driver' => (string) config('services.online_meetings.driver')]))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->iconColor('warning')
                    ->visible(fn (): bool => ! self::graphDriver()),
                Section::make()->description(__('settings.online_meetings.hint'))->columns(2)->schema([
                    Toggle::make('enabled')->label(__('settings.online_meetings.enabled'))
                        ->helperText(__('settings.online_meetings.enabled_hint'))
                        ->columnSpanFull(),
                    TextInput::make('tenant_id')->label(__('settings.online_meetings.attributes.tenant_id'))
                        ->helperText(__('settings.online_meetings.tenant_id_hint'))
                        ->maxLength(255)
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('client_id')->label(__('settings.online_meetings.attributes.client_id'))
                        ->helperText(__('settings.online_meetings.client_id_hint'))
                        ->maxLength(36)
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('client_secret')->label(__('settings.online_meetings.attributes.client_secret'))
                        ->helperText(fn (): string => __(filled(Setting::group(TeamsConnection::SETTING)['client_secret'] ?? null)
                            ? 'settings.online_meetings.secret_saved'
                            : 'settings.online_meetings.secret_hint'))
                        ->password()
                        ->revealable(false)
                        ->autocomplete('new-password')
                        ->maxLength(255)
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('organizer')->label(__('settings.online_meetings.attributes.organizer'))
                        ->helperText(__('settings.online_meetings.organizer_hint'))
                        ->email()
                        ->maxLength(254)
                        ->extraInputAttributes(['dir' => 'ltr']),
                ]),
            ]);
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
                        Action::make('testConnection')->label(__('settings.online_meetings.test'))
                            ->tooltip(__('settings.online_meetings.test_hint'))
                            ->icon(Heroicon::OutlinedSignal)
                            ->color('gray')
                            ->action('testConnection'),
                    ])->visible(fn (): bool => static::canWrite()),
                ]),
        ]);
    }

    public function save(UpdateOnlineMeetingSettings $action): void
    {
        $this->authorizeWrite();

        try {
            /** @var array<string, mixed> $state */
            $state = $this->form->getState();
        } finally {
            // السر لا يعود إلى المتصفح، ولو رُفض النموذج
            $this->data['client_secret'] = null;
        }

        try {
            $resynced = $action->handle($this->actor(), $state);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key) => ['data.'.$key => $messages])->all(),
            );
        }

        Notification::make()->success()->title(__('settings.saved'))
            ->body($resynced > 0 ? __('settings.online_meetings.resynced', ['count' => number_format($resynced)]) : null)
            ->send();

        // الإطفاء لا يلغي في تيمز ما أُلغي هنا بعده، فيُنبَّه إن بقيت روابط قائمة
        $linked = Meeting::query()->where(fn ($query) => $query->whereNotNull('online_meeting_id')->orWhere('online_create_pending', true))->count();

        if (! ($state['enabled'] ?? false) && $linked > 0) {
            Notification::make()->warning()->persistent()
                ->title(__('settings.online_meetings.disabled_with_links', ['count' => number_format($linked)]))
                ->send();
        }
    }

    public function testConnection(TestOnlineMeetingsConnection $action): void
    {
        $this->authorizeWrite();

        // الاختبار للمحفوظ، والسر المكتوب قبل الحفظ لا يعود إلى المتصفح
        $this->data['client_secret'] = null;

        try {
            $failure = $action->handle($this->actor());
        } catch (ValidationException $exception) {
            Notification::make()->warning()->title($exception->getMessage())->send();

            return;
        }

        if ($failure === null) {
            Notification::make()->success()->title(__('settings.online_meetings.test_ok'))
                // الاتصال سليم، لكن الاجتماعات لا تمر به ما لم يكن المشغّل graph
                ->body(self::graphDriver() ? null : __('settings.online_meetings.driver_warning'))
                ->send();

            return;
        }

        Notification::make()->danger()
            ->title($failure->getLabel())
            ->body(__('assembly.online_failure_hints.'.$failure->value))
            ->persistent()
            ->send();
    }

    private static function graphDriver(): bool
    {
        return config('services.online_meetings.driver') === 'graph';
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
