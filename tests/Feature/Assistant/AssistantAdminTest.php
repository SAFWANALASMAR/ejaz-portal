<?php

use App\Actions\Assistant\FlagAssistantAnswer;
use App\Actions\Assistant\SaveAssistantSettings;
use App\Enums\ActivityEvent;
use App\Enums\AssistantMode;
use App\Enums\RoleName;
use App\Filament\Pages\AssistantSettingsPage;
use App\Filament\Resources\AssistantFaqs\AssistantFaqResource;
use App\Filament\Resources\AssistantFaqs\Pages\CreateAssistantFaq;
use App\Filament\Resources\AssistantGuides\AssistantGuideResource;
use App\Filament\Resources\AssistantGuides\Pages\CreateAssistantGuide;
use App\Filament\Resources\AssistantLogs\AssistantLogResource;
use App\Filament\Resources\AssistantLogs\Pages\ViewAssistantLog;
use App\Models\AssistantFaq;
use App\Models\AssistantLog;
use App\Models\AssistantSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| إعدادات المساعد وأسئلته الشائعة ودليله وسجله في اللوحة: للمشرف العام وحده (ai_assistant.manage)، والمفتاح مشفّر
| لا يُعاد إلى المتصفح ولا يدخل سجل العمليات.
*/

it('opens the assistant screens to the administrators only', function () {
    $pages = [AssistantSettingsPage::getUrl(), AssistantFaqResource::getUrl(), AssistantGuideResource::getUrl(), AssistantLogResource::getUrl()];

    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));
    foreach ($pages as $url) {
        $this->get($url)->assertOk();
    }

    foreach ([RoleName::ContentEditor, RoleName::BoardMember, RoleName::Secretary] as $role) {
        $this->actingAs(userWithRole($role));

        foreach ($pages as $url) {
            $this->get($url)->assertForbidden();
        }
    }
});

it('refuses the settings action to anyone but the super admin', function () {
    expect(fn () => app(SaveAssistantSettings::class)->handle(userWithRole(RoleName::ContentEditor), ['is_enabled' => true]))
        ->toThrow(AuthorizationException::class);
});

it('stores the API key encrypted, never sends it back and never logs it', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));

    Livewire::test(AssistantSettingsPage::class)
        ->fillForm(['is_enabled' => true, 'provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'api_key' => 'sk-ant-secret-123'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('data.api_key', null);

    $raw = DB::table('assistant_settings')->value('api_key');
    expect($raw)->not->toBeNull()->not->toContain('sk-ant-secret-123')
        ->and(AssistantSettings::query()->sole()->api_key)->toBe('sk-ant-secret-123');

    // لا يعود إلى المتصفح: لا في الصفحة ولا في حالة Livewire
    $this->get(AssistantSettingsPage::getUrl())->assertOk()->assertDontSee('sk-ant-secret-123')->assertDontSee($raw);
    Livewire::test(AssistantSettingsPage::class)->assertSet('data.api_key', null)->assertDontSee('sk-ant-secret-123');

    // ولا في سجل العمليات، بل حدث تغييره بلا قيمته
    $activities = Activity::query()->get();
    expect($activities->toJson(JSON_UNESCAPED_UNICODE))->not->toContain('sk-ant-secret-123')->not->toContain($raw)
        ->and($activities->where('event', ActivityEvent::AssistantKeyChanged->value))->toHaveCount(1);

    // ولا في الذاكرة المؤقتة نصاً صريحاً
    expect(json_encode(AssistantSettings::current()->getAttributes()))->not->toContain('sk-ant-secret-123');
});

it('keeps the saved key when the field is left empty, and clears it on request', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $action = app(SaveAssistantSettings::class);

    $action->handle($admin, ['provider' => 'anthropic', 'model' => 'm', 'api_key' => 'sk-one']);
    $action->handle($admin, ['provider' => 'anthropic', 'model' => 'm2', 'api_key' => null]);
    expect(AssistantSettings::query()->sole()->api_key)->toBe('sk-one');

    $action->handle($admin, ['provider' => 'anthropic', 'model' => 'm2', 'clear_api_key' => true]);
    expect(AssistantSettings::query()->sole()->api_key)->toBeNull()
        ->and(Activity::query()->where('event', ActivityEvent::AssistantKeyChanged->value)->count())->toBe(2);
});

it('survives a key encrypted with an old application key', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    AssistantSettings::query()->create(['provider' => 'anthropic', 'model' => 'm']);
    DB::table('assistant_settings')->update(['api_key' => 'eyJpdiI6ImJhZCIsInZhbHVlIjoiYmFkIn0=']);

    $settings = AssistantSettings::query()->sole();
    expect($settings->storedKeyUnreadable())->toBeTrue()
        ->and($settings->apiKey())->toBeNull()
        ->and($settings->hasUsableProvider())->toBeFalse();

    $this->actingAs($admin)->get(AssistantSettingsPage::getUrl())->assertOk();

    app(SaveAssistantSettings::class)->handle($admin, ['provider' => 'anthropic', 'model' => 'm', 'api_key' => 'sk-new']);
    expect(AssistantSettings::query()->sole()->api_key)->toBe('sk-new');
});

it('sends the key only to provider hosts allowed on the server', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $action = app(SaveAssistantSettings::class);

    expect(fn () => $action->handle($admin, ['provider' => 'openai_compatible', 'model' => 'm', 'base_url' => 'https://attacker.example/v1', 'api_key' => 'sk-x']))
        ->toThrow(ValidationException::class);

    // ولا يُرسل مفتاح البيئة إلى نطاق غير مسموح ولو حُفظ الإعداد مباشرة في القاعدة
    config(['assistant.api_keys.openai_compatible' => 'sk-env']);
    $settings = new AssistantSettings(['provider' => 'openai_compatible', 'model' => 'm', 'base_url' => 'https://attacker.example/v1']);
    expect($settings->hasUsableProvider())->toBeFalse();

    config(['assistant.allowed_hosts' => ['attacker.example']]);
    expect($settings->hasUsableProvider())->toBeTrue();
});

it('asks for the key again before sending a saved key to another provider or host', function () {
    config(['assistant.allowed_hosts' => ['api.anthropic.com', 'api.openai.com', 'models.example.sa']]);
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $action = app(SaveAssistantSettings::class);
    $action->handle($admin, ['provider' => 'openai_compatible', 'model' => 'm', 'base_url' => 'https://api.openai.com/v1', 'api_key' => 'sk-openai']);

    // نطاق آخر بلا مفتاح جديد: يُرفض في الحفظ وفي الاختبار، ولا يُرسل شيء
    Http::fake();
    expect(fn () => $action->handle($admin, ['provider' => 'openai_compatible', 'model' => 'm', 'base_url' => 'https://models.example.sa/v1']))
        ->toThrow(ValidationException::class);

    $this->actingAs($admin);
    Livewire::test(AssistantSettingsPage::class)
        ->fillForm(['provider' => 'openai_compatible', 'model' => 'm', 'base_url' => 'https://models.example.sa/v1'])
        ->call('testConnection')
        ->assertHasFormErrors(['api_key']);
    Http::assertNothingSent();

    // ومزود آخر كذلك
    expect(fn () => $action->handle($admin, ['provider' => 'anthropic', 'model' => 'm']))->toThrow(ValidationException::class);

    // وبمفتاح جديد يُقبل، وتغيير النموذج وحده لا يطلبه
    $action->handle($admin, ['provider' => 'openai_compatible', 'model' => 'm', 'base_url' => 'https://models.example.sa/v1', 'api_key' => 'sk-local']);
    $action->handle($admin, ['provider' => 'openai_compatible', 'model' => 'm2', 'base_url' => 'https://models.example.sa/v1']);
    expect(AssistantSettings::query()->sole())->api_key->toBe('sk-local')->model->toBe('m2');
});

it('never sends one provider environment key to another provider when testing the connection', function () {
    config(['assistant.api_keys.anthropic' => 'sk-anthropic-env']);
    $admin = userWithRole(RoleName::TechnicalAdmin);
    app(SaveAssistantSettings::class)->handle($admin, ['provider' => 'anthropic', 'model' => 'claude-haiku-5-5']);
    Http::fake();

    $this->actingAs($admin);
    Livewire::test(AssistantSettingsPage::class)
        ->fillForm(['provider' => 'openai_compatible', 'model' => 'gpt-x', 'base_url' => 'https://api.openai.com/v1'])
        ->call('testConnection')
        ->assertNotified(__('assistant.admin.settings.test_incomplete'));

    Http::assertNothingSent();
});

it('tests the connection with the stored key only for the provider and host it was saved for', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    app(SaveAssistantSettings::class)->handle($admin, ['provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'api_key' => 'sk-stored']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'OK']], 'usage' => ['input_tokens' => 3, 'output_tokens' => 1]])]);

    $this->actingAs($admin);
    Livewire::test(AssistantSettingsPage::class)
        ->fillForm(['provider' => 'anthropic', 'model' => 'claude-sonnet-5-5'])
        ->call('testConnection')
        ->assertNotified(__('assistant.admin.settings.test_ok'));

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'sk-stored'));
});

it('clears the typed key from the page state when saving fails', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));

    Livewire::test(AssistantSettingsPage::class)
        ->fillForm(['provider' => 'openai_compatible', 'model' => 'm', 'base_url' => 'https://attacker.example/v1', 'api_key' => 'sk-typed-secret'])
        ->call('save')
        ->assertHasFormErrors(['base_url'])
        ->assertSet('data.api_key', null)
        ->assertDontSee('sk-typed-secret');

    expect(AssistantSettings::query()->exists())->toBeFalse();
});

it('clears the typed key from the page state after testing the connection', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'OK']], 'usage' => ['input_tokens' => 3, 'output_tokens' => 1]])]);

    Livewire::test(AssistantSettingsPage::class)
        ->fillForm(['provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'api_key' => 'sk-typed'])
        ->call('testConnection')
        ->assertSet('data.api_key', null)
        ->assertDontSee('sk-typed');
});

it('does not enable generation without a provider, a cap and prices', function (array $data) {
    $admin = userWithRole(RoleName::TechnicalAdmin);

    expect(fn () => app(SaveAssistantSettings::class)->handle($admin, ['generation_enabled' => true, ...$data]))->toThrow(ValidationException::class);
})->with([
    'no provider' => [['monthly_cap' => 5, 'input_price' => 1, 'output_price' => 5]],
    'no key' => [['provider' => 'anthropic', 'model' => 'm', 'monthly_cap' => 5, 'input_price' => 1, 'output_price' => 5]],
    'no cap' => [['provider' => 'fake', 'input_price' => 1, 'output_price' => 5]],
    'no prices' => [['provider' => 'fake', 'monthly_cap' => 5]],
]);

it('validates the provider settings', function (array $data) {
    $admin = userWithRole(RoleName::TechnicalAdmin);

    expect(fn () => app(SaveAssistantSettings::class)->handle($admin, $data))->toThrow(ValidationException::class);
})->with([
    'unknown provider' => [['provider' => 'other']],
    'base url with credentials' => [['base_url' => 'https://user:pass@models.example.sa/v1']],
    'base url with query' => [['base_url' => 'https://models.example.sa/v1?x=1']],
    'base url not http' => [['base_url' => 'file:///etc/passwd']],
    'model with spaces' => [['model' => 'a model']],
    'key with spaces' => [['api_key' => 'sk key']],
    'too many suggestions' => [['suggested_questions' => array_fill(0, 7, 'سؤال')]],
    'retention too long' => [['log_retention_days' => 99999]],
    // سقف صفر يشغّل التوليد ولا يجيب أبداً (مراجعة ما بعد v0.12.0)
    'zero monthly cap' => [['monthly_cap' => 0]],
    'monthly cap stored as zero' => [['monthly_cap' => 0.001]],
]);

it('accepts only https base URLs in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => SaveAssistantSettings::validate(['base_url' => 'http://models.example.sa/v1']))->toThrow(ValidationException::class)
        ->and(SaveAssistantSettings::validate(['base_url' => 'https://models.example.sa/v1/'])['base_url'])->toBe('https://models.example.sa/v1');

    app()->detectEnvironment(fn () => 'testing');
});

it('tests the connection with the typed values before saving', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(['content' => [['type' => 'text', 'text' => 'OK']], 'usage' => ['input_tokens' => 3, 'output_tokens' => 1]])
        ->push(['error' => 'bad key'], 401)]);

    Livewire::test(AssistantSettingsPage::class)
        ->fillForm(['provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'api_key' => 'sk-typed'])
        ->call('testConnection')
        ->assertNotified(__('assistant.admin.settings.test_ok'));

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'sk-typed'));
    expect(AssistantSettings::query()->exists())->toBeFalse();

    Livewire::test(AssistantSettingsPage::class)
        ->fillForm(['provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'api_key' => 'sk-typed'])
        ->call('testConnection')
        ->assertNotified(__('assistant.admin.settings.test_failed'));
});

it('creates approved questions with a source page in the portal', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));

    Livewire::test(CreateAssistantFaq::class)
        ->fillForm(['question' => 'كيف أراسل الجمعية؟', 'answer' => "من صفحة التواصل.\nسطر ثانٍ.", 'source_url' => 'https://evil.example', 'status' => 'approved'])
        ->call('create')
        ->assertHasFormErrors(['source_url']);

    Livewire::test(CreateAssistantFaq::class)
        ->fillForm(['question' => 'كيف أراسل الجمعية؟', 'answer' => "من صفحة التواصل.\nسطر ثانٍ.", 'source_url' => '/contact', 'status' => 'approved'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(AssistantFaq::query()->sole()->isActive())->toBeTrue();
});

it('accepts guide articles only for existing public pages', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));
    $data = ['title' => 'كيف أراسلكم؟', 'slug' => 'contact-us-test', 'steps' => [['step' => 'افتح الصفحة.']], 'status' => 'reviewed'];

    foreach (['filament.admin.pages.dashboard', 'governance.show', 'no.such.route'] as $route) {
        Livewire::test(CreateAssistantGuide::class)->fillForm([...$data, 'route_name' => $route])->call('create')->assertHasFormErrors(['route_name']);
    }

    Livewire::test(CreateAssistantGuide::class)->fillForm([...$data, 'route_name' => 'contact'])->call('create')->assertHasNoFormErrors();
});

it('lets the super admin flag a wrong answer but never edit or delete the log', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $log = AssistantLog::query()->create(['question' => 'سؤال', 'answer' => 'جواب خاطئ', 'mode' => AssistantMode::Generated]);

    $this->actingAs($admin);
    Livewire::test(ViewAssistantLog::class, ['record' => $log->getKey()])
        ->callAction('flag', ['flag_note' => 'يلزم سؤال شائع'])
        ->assertHasNoActionErrors();

    expect($log->fresh())->is_flagged->toBeTrue()->flag_note->toBe('يلزم سؤال شائع')
        ->and($admin->can('delete', $log))->toBeFalse()
        ->and($admin->can('create', AssistantLog::class))->toBeFalse()
        ->and(Activity::query()->where('event', ActivityEvent::AssistantAnswerFlagged->value)->exists())->toBeTrue();

    expect(fn () => app(FlagAssistantAnswer::class)->handle(userWithRole(RoleName::ContentEditor), $log, false))->toThrow(AuthorizationException::class);
});

it('deletes conversation logs older than the retention period', function () {
    enableAssistant(['log_retention_days' => 30]);
    $old = AssistantLog::query()->create(['question' => 'قديم', 'mode' => AssistantMode::Generated]);
    $old->forceFill(['created_at' => now()->subDays(31)])->save();
    $recent = AssistantLog::query()->create(['question' => 'حديث', 'mode' => AssistantMode::Generated]);

    Artisan::call('model:prune', ['--model' => [AssistantLog::class]]);

    expect(AssistantLog::query()->pluck('id')->all())->toBe([$recent->id]);
});

it('keeps logs for the provisional default when no retention is set', function () {
    expect(AssistantSettings::current()->retentionDays())->toBe(AssistantSettings::DEFAULT_RETENTION_DAYS);
});
