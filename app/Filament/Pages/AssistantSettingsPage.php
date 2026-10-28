<?php

namespace App\Filament\Pages;

use App\Actions\Assistant\SaveAssistantSettings;
use App\Actions\Assistant\TestAssistantConnection;
use App\Enums\AssistantProvider;
use App\Filament\Concerns\ReadableByOverseers;
use App\Models\AssistantSettings;
use App\Models\AssistantUsage;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * إعدادات المساعد الذكي (requirements/10): التشغيل، والتوليد، والمزود ومفتاحه، والسقف، والترحيب، والسجل.
 * الصلاحية ai_assistant.manage (المشرف العام وحده). والمفتاح لا يُعاد إلى المتصفح أبداً: الحقل فارغ دائماً،
 * والمكتوب فيه يُرسل مرة عند الحفظ أو الاختبار ثم يُفرغ.
 *
 * @property-read Schema $form
 */
class AssistantSettingsPage extends Page
{
    use ReadableByOverseers;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'assistant-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected static function canWrite(): bool
    {
        return auth()->user()?->can('ai_assistant.manage') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('assistant.admin.settings.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.assistant');
    }

    public function getTitle(): string|Htmlable
    {
        return __('assistant.admin.settings.title');
    }

    public function mount(): void
    {
        $settings = AssistantSettings::query()->orderBy('id')->first() ?? new AssistantSettings(['is_enabled' => false, 'generation_enabled' => false]);

        $this->form->fill([
            // الحقول بأسمائها، فلا يُقرأ المفتاح ولا يُفك أصلاً
            ...$settings->only(['is_enabled', 'generation_enabled', 'analysis_enabled', 'model', 'base_url', 'monthly_cap', 'input_price', 'output_price', 'welcome_text', 'log_retention_days']),
            'provider' => $settings->provider?->value,
            'suggested_questions' => (array) ($settings->suggested_questions ?? []),
            'api_key' => null,
            'clear_api_key' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $saved = AssistantSettings::query()->orderBy('id')->first();
        $hasKey = $saved?->hasStoredKey() ?? false;

        return $schema
            ->statePath('data')
            ->disabled(fn (): bool => ! static::canWrite())
            ->components([
                Tabs::make()->tabs([
                    Tab::make(__('assistant.admin.settings.tabs.general'))->schema([
                        Toggle::make('is_enabled')->label(__('assistant.admin.settings.is_enabled'))
                            ->helperText(__('assistant.admin.settings.is_enabled_hint')),
                        Toggle::make('generation_enabled')->label(__('assistant.admin.settings.generation_enabled'))
                            ->helperText(__('assistant.admin.settings.generation_enabled_hint')),
                        Toggle::make('analysis_enabled')->label(__('assistant.admin.settings.analysis_enabled'))
                            ->helperText(__('assistant.admin.settings.analysis_enabled_hint')),
                        Textarea::make('welcome_text')->label(__('assistant.admin.settings.welcome_text'))
                            ->helperText(__('assistant.admin.settings.welcome_text_hint'))
                            ->rows(3)
                            ->maxLength(600),
                        Section::make(__('assistant.admin.settings.suggested_questions'))
                            ->description(__('assistant.admin.settings.suggested_questions_hint'))
                            ->schema([
                                Repeater::make('suggested_questions')
                                    ->hiddenLabel()
                                    ->simple(TextInput::make('question')->label(__('assistant.admin.settings.suggested_question'))->required()->maxLength(150))
                                    ->defaultItems(0)
                                    ->maxItems(6),
                            ]),
                    ]),
                    Tab::make(__('assistant.admin.settings.tabs.provider'))->schema([
                        Select::make('provider')->label(__('assistant.admin.settings.provider'))
                            ->helperText(__('assistant.admin.settings.provider_hint'))
                            ->options(collect(AssistantProvider::available())->mapWithKeys(fn (AssistantProvider $provider): array => [$provider->value => $provider->getLabel()])->all()),
                        TextInput::make('model')->label(__('assistant.admin.settings.model'))
                            ->helperText(__('assistant.admin.settings.model_hint'))
                            ->maxLength(150)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('base_url')->label(__('assistant.admin.settings.base_url'))
                            ->helperText(__('assistant.admin.settings.base_url_hint'))
                            ->maxLength(300)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('api_key')->label(__('assistant.admin.settings.api_key'))
                            ->password()
                            ->autocomplete('new-password')
                            ->placeholder($hasKey ? __('assistant.admin.settings.api_key_saved') : __('assistant.admin.settings.api_key_missing'))
                            ->helperText(AssistantSettings::providersWithEnvironmentKey() !== []
                                ? __('assistant.admin.settings.api_key_env', ['providers' => implode('، ', array_map(fn (AssistantProvider $provider): string => $provider->getLabel(), AssistantSettings::providersWithEnvironmentKey()))])
                                : __('assistant.admin.settings.api_key_hint'))
                            ->maxLength(500)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        Toggle::make('clear_api_key')->label(__('assistant.admin.settings.clear_api_key'))->visible($hasKey),
                    ]),
                    Tab::make(__('assistant.admin.settings.tabs.budget'))->schema([
                        TextInput::make('monthly_cap')->label(__('assistant.admin.settings.monthly_cap'))
                            ->helperText(__('assistant.admin.settings.monthly_cap_hint').' '.__('assistant.admin.settings.month_cost', ['cost' => number_format(AssistantUsage::monthCost(), 2)]))
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(100000),
                        Section::make()->description(__('assistant.admin.settings.prices_hint'))->columns(2)->schema([
                            TextInput::make('input_price')->label(__('assistant.admin.settings.input_price'))->numeric()->minValue(0)->maxValue(10000),
                            TextInput::make('output_price')->label(__('assistant.admin.settings.output_price'))->numeric()->minValue(0)->maxValue(10000),
                        ]),
                    ]),
                    Tab::make(__('assistant.admin.settings.tabs.log'))->schema([
                        TextInput::make('log_retention_days')->label(__('assistant.admin.settings.log_retention_days'))
                            ->helperText(__('assistant.admin.settings.log_retention_days_hint', ['days' => AssistantSettings::DEFAULT_RETENTION_DAYS]))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(AssistantSettings::MAX_RETENTION_DAYS),
                    ]),
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
                        Action::make('test')->label(__('assistant.admin.settings.test'))->color('gray')->action('testConnection'),
                    ])->visible(fn (): bool => static::canWrite()),
                ]),
        ]);
    }

    public function save(SaveAssistantSettings $action): void
    {
        $this->authorizeWrite();

        /** @var User $user */
        $user = auth()->user();

        // المفتاح المكتوب يُفرغ نجح الحفظ أو فشل تحققه، فلا يبقى في حالة Livewire المحفوظة في الصفحة (مراجعة المشرف، ٤)
        try {
            $this->withActionErrors(fn () => $action->handle($user, $this->form->getState()));
        } finally {
            $this->data['api_key'] = null;
        }

        $this->data['clear_api_key'] = false;

        Notification::make()->success()->title(__('assistant.admin.settings.saved'))->send();
    }

    public function testConnection(TestAssistantConnection $action): void
    {
        $this->authorizeWrite();

        /** @var User $user */
        $user = auth()->user();

        // المفتاح المكتوب يُرسل مرة ثم يُفرغ، فلا يبقى في حالة Livewire المحفوظة في الصفحة (المراجعة الأمنية، ن5)
        try {
            $result = $this->withActionErrors(fn (): ?bool => $action->handle($user, $this->form->getState()));
        } finally {
            $this->data['api_key'] = null;
        }

        match ($result) {
            true => Notification::make()->success()->title(__('assistant.admin.settings.test_ok'))->send(),
            false => Notification::make()->danger()->title(__('assistant.admin.settings.test_failed'))->send(),
            null => Notification::make()->warning()->title(__('assistant.admin.settings.test_incomplete'))->send(),
        };
    }

    /**
     * أخطاء الإجراء بمسارات الإعدادات، والحقول في النموذج تحت data.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withActionErrors(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ['data.'.$key => $messages])->all(),
            );
        }
    }
}
