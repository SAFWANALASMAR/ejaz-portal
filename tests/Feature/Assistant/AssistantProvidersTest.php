<?php

use App\Contracts\AiAssistant;
use App\Enums\AssistantMode;
use App\Enums\AssistantProvider;
use App\Models\AssistantSettings;
use App\Models\Page;
use App\Services\Assistant\AnthropicAiAssistant;
use App\Services\Assistant\AssistantProviderException;
use App\Services\Assistant\AssistantProviders;
use App\Services\Assistant\FakeAiAssistant;
use App\Services\Assistant\OpenAiCompatibleAiAssistant;
use App\Support\Assistant\AnswerComposer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| تطبيقا المزود الحقيقيان بعميل HTTP في لارافيل (Http::fake، بلا شبكة)، والوهمي، واختيار المزود من الإعدادات.
*/

it('calls the Anthropic Messages API with only the model, limit, system and one user message', function () {
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'thinking', 'thinking' => ''], ['type' => 'text', 'text' => 'جواب [1]']],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 120, 'output_tokens' => 30],
    ])]);

    $reply = (new AnthropicAiAssistant('https://api.anthropic.com', 'sk-test-key', 'claude-haiku-5-5'))->answer('التعليمات', 'الرسالة');

    expect($reply->text)->toBe('جواب [1]')->and($reply->inputTokens)->toBe(120)->and($reply->outputTokens)->toBe(30);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'sk-test-key')
            && $request->hasHeader('anthropic-version', AnthropicAiAssistant::VERSION)
            && array_keys($request->data()) === ['model', 'max_tokens', 'system', 'messages']
            && $request['system'] === 'التعليمات'
            && $request['messages'] === [['role' => 'user', 'content' => 'الرسالة']];
    });
});

it('treats an Anthropic refusal as no answer', function () {
    Http::fake(['*' => Http::response(['content' => [], 'stop_reason' => 'refusal', 'usage' => ['input_tokens' => 5, 'output_tokens' => 0]])]);

    expect((new AnthropicAiAssistant('https://api.anthropic.com', 'k', 'm'))->answer('s', 'm')->text)->toBe(AnswerComposer::NO_ANSWER);
});

it('calls an OpenAI compatible API at its base URL with a bearer token', function () {
    Http::fake(['models.example.sa/*' => Http::response([
        'choices' => [['message' => ['role' => 'assistant', 'content' => 'جواب'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 90, 'completion_tokens' => 12],
    ])]);

    $reply = (new OpenAiCompatibleAiAssistant('https://models.example.sa/v1', 'local-key', 'arabic-model'))->answer('التعليمات', 'الرسالة');

    expect($reply->text)->toBe('جواب')->and($reply->inputTokens)->toBe(90)->and($reply->outputTokens)->toBe(12);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://models.example.sa/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer local-key')
        && array_keys($request->data()) === ['model', 'max_completion_tokens', 'messages']
        && $request['max_completion_tokens'] === 600
        && $request['messages'] === [['role' => 'system', 'content' => 'التعليمات'], ['role' => 'user', 'content' => 'الرسالة']]);
});

it('sends max_tokens instead to older compatible servers when configured', function (string $setting, string $expected) {
    config(['assistant.openai_token_parameter' => $setting]);
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'جواب'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]])]);

    (new OpenAiCompatibleAiAssistant('https://models.example.sa/v1', 'k', 'm'))->answer('s', 'm');

    Http::assertSent(fn (Request $request): bool => array_keys($request->data()) === ['model', $expected, 'messages']);
})->with([
    'older servers' => ['max_tokens', 'max_tokens'],
    'default' => ['max_completion_tokens', 'max_completion_tokens'],
    'anything else falls back' => ['tokens', 'max_completion_tokens'],
]);

it('waits for the provider no longer than the configured timeout', function (string $class, string $url) {
    $options = [];
    Http::fake(function (Request $request, array $sent) use (&$options) {
        $options = $sent;

        throw new ConnectionException('cURL error 28: Operation timed out');
    });

    expect(fn () => (new $class($url, 'k', 'm'))->answer('s', 'm'))->toThrow(AssistantProviderException::class);
    expect($options['timeout'])->toBe(15)->and($options['connect_timeout'])->toBe(5);
})->with([
    'anthropic' => [AnthropicAiAssistant::class, 'https://api.anthropic.com'],
    'openai compatible' => [OpenAiCompatibleAiAssistant::class, 'https://models.example.sa/v1'],
]);

it('never follows redirects with the key', function (string $class, string $url) {
    $options = [];
    Http::fake(function (Request $request, array $sent) use (&$options) {
        $options = $sent;

        return Http::response('', 302, ['Location' => 'https://attacker.example/']);
    });

    expect(fn () => (new $class($url, 'k', 'm'))->answer('s', 'm'))->toThrow(AssistantProviderException::class);
    expect($options['allow_redirects'])->toBeFalse();
})->with([
    'anthropic' => [AnthropicAiAssistant::class, 'https://api.anthropic.com'],
    'openai compatible' => [OpenAiCompatibleAiAssistant::class, 'https://models.example.sa/v1'],
]);

it('estimates the tokens when the provider returns no usage, so the cap still counts', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'جواب طويل نسبياً'], 'finish_reason' => 'stop']]])]);

    $reply = (new OpenAiCompatibleAiAssistant('https://models.example.sa/v1', 'k', 'm'))->answer(str_repeat('ت', 100), str_repeat('ر', 300));

    expect($reply->inputTokens)->toBe(200)->and($reply->outputTokens)->toBeGreaterThan(0);
});

it('reports provider errors without the key or the question', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'invalid x-api-key sk-secret']], 401)]);

    try {
        (new AnthropicAiAssistant('https://api.anthropic.com', 'sk-secret', 'm'))->answer('s', 'سؤال خاص');
        $this->fail('Expected an exception.');
    } catch (AssistantProviderException $exception) {
        expect($exception->getMessage())->not->toContain('sk-secret')->not->toContain('سؤال خاص');
    }
});

it('answers busy when a real provider times out, end to end', function () {
    enableAssistant(['provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'api_key' => 'sk-test']);
    app()->forgetInstance(AiAssistant::class);
    Page::factory()->create(['slug' => 'manuscripts', 'title' => 'معرض المخطوطات', 'summary' => 'المخطوطات النادرة']);
    Http::fake(['api.anthropic.com/*' => fn () => throw new ConnectionException('timed out')]);

    $this->postJson(route('assistant.ask'), ['question' => 'أين المخطوطات؟'])->assertOk()
        ->assertJson(['mode' => AssistantMode::Busy->value, 'answer' => __('assistant.busy'), 'contact' => true]);
});

it('builds the provider from the settings, preferring the environment key', function () {
    config(['assistant.allowed_hosts' => ['api.anthropic.com', 'models.example.sa']]);
    $settings = new AssistantSettings(['provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'api_key' => 'from-panel']);

    expect(AssistantProviders::make($settings))->toBeInstanceOf(AnthropicAiAssistant::class)
        ->and($settings->apiKey())->toBe('from-panel');

    // مفتاح البيئة مربوط بمزوده: مفتاح المتوافق مع OpenAI لا يُرسل إلى Anthropic، ومفتاح Anthropic يُقدَّم لها
    config(['assistant.api_keys.openai_compatible' => 'openai-env']);
    expect($settings->apiKey())->toBe('from-panel');

    config(['assistant.api_keys.anthropic' => 'anthropic-env']);
    expect($settings->apiKey())->toBe('anthropic-env')
        ->and(AssistantSettings::providersWithEnvironmentKey())->toHaveCount(2);

    $settings->provider = AssistantProvider::OpenAiCompatible;
    expect($settings->apiKey())->toBe('openai-env');

    // المتوافق مع OpenAI يلزمه عنوانه الأساسي
    $openai = new AssistantSettings(['provider' => 'openai_compatible', 'model' => 'm', 'api_key' => 'k']);
    expect($openai->hasUsableProvider())->toBeFalse();
    $openai->base_url = 'https://models.example.sa/v1';
    expect(AssistantProviders::make($openai))->toBeInstanceOf(OpenAiCompatibleAiAssistant::class);
});

it('refuses the fake provider in production', function () {
    $settings = new AssistantSettings(['provider' => 'fake']);
    expect($settings->hasUsableProvider())->toBeTrue();

    app()->detectEnvironment(fn () => 'production');

    expect($settings->hasUsableProvider())->toBeFalse()
        ->and(fn () => (new FakeAiAssistant)->answer('s', 'm'))->toThrow(RuntimeException::class);

    app()->detectEnvironment(fn () => 'testing');
    Cache::flush();
});
