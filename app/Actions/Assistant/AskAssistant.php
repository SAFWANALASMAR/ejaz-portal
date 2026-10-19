<?php

namespace App\Actions\Assistant;

use App\Contracts\AiAssistant;
use App\Enums\AssistantMode;
use App\Enums\AssistantSource;
use App\Models\AssistantChunk;
use App\Models\AssistantLog;
use App\Models\AssistantSettings;
use App\Services\Assistant\AssistantProviderException;
use App\Support\Assistant\AnswerComposer;
use App\Support\Assistant\AssistantRetriever;
use App\Support\Assistant\ConcurrencyGate;
use App\Support\Assistant\CurrentPage;
use App\Support\Assistant\PersonalData;
use App\Support\Assistant\PromptBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * سؤال واحد للمساعد (requirements/10). الحد لكل زائر على المسار، والتشغيل في المتحكم. ثم:
 * 1. يُحذف من السؤال ما يشبه البيانات الشخصية.
 * 2. بلا مزود صالح، أو التوليد مطفأ، أو بلغ السقف الشهري: وضع الدليل، أقرب الأسئلة المعتمدة ومقالات الدليل بلا توليد.
 * 3. وإلا تُسترجع المقاطع، وبلا مقطع مطابق لا يُرسل شيء إلى المزود (الرد الاحتياطي).
 * 4. يُرسل إلى المزود التعليمات والمقاطع والسؤال وحدها، في خانة من الطلبات المتزامنة وبمهلته، وإلا «المساعد مشغول».
 * وكل سؤال يُسجَّل بجوابه ومصادره وتكلفته التقديرية، بلا عنوان ولا مستخدم.
 *
 * @phpstan-type Answer array{id: string, mode: string, answer: string, sources: list<array{title: string, url: string, navigate: bool}>, contact: bool}
 */
class AskAssistant
{
    public function __construct(private readonly CheckAssistantBudget $budget) {}

    /**
     * @return Answer
     */
    public function handle(string $question, ?string $page = null): array
    {
        $settings = AssistantSettings::current();
        $question = PersonalData::redact(trim($question));
        $path = CurrentPage::path($page);

        if (! $settings->generation_enabled || ! $settings->hasUsableProvider() || ! $this->budget->allows($settings)) {
            return $this->guide($question, $path);
        }

        $chunks = AssistantRetriever::search($question, (int) config('assistant.max_chunks'), currentPath: $path);

        if ($chunks->isEmpty()) {
            return $this->record($question, AssistantMode::NoMatch, __('assistant.no_answer'), [], contact: true);
        }

        $message = PromptBuilder::message($question, $chunks, $path !== null ? CurrentPage::name($path) : null, $path);

        // الحد العام للبوابة في الدقيقة قبل الخانات المتزامنة: ما زاد «مشغول» بلا اتصال بالمزود
        if (! $this->budget->takeSlot()) {
            return $this->record($question, AssistantMode::Busy, __('assistant.busy'), [], contact: true, settings: $settings);
        }

        try {
            $reply = ConcurrencyGate::run(fn () => app(AiAssistant::class)->answer(PromptBuilder::SYSTEM, $message));
        } catch (AssistantProviderException $exception) {
            // السبب وحده في السجل، بلا السؤال ولا المفتاح
            Log::warning('AI assistant provider failed: '.$exception->getMessage());
            $reply = null;
        }

        if ($reply === null) {
            return $this->record($question, AssistantMode::Busy, __('assistant.busy'), [], contact: true, settings: $settings);
        }

        $cost = $this->budget->cost($settings, $reply->inputTokens, $reply->outputTokens);
        $this->budget->record($cost);
        $usage = ['input_tokens' => $reply->inputTokens, 'output_tokens' => $reply->outputTokens, 'cost' => $cost];

        if (AnswerComposer::isNoAnswer($reply->text)) {
            return $this->record($question, AssistantMode::NoMatch, __('assistant.no_answer'), [], contact: true, settings: $settings, usage: $usage);
        }

        $answer = AnswerComposer::clean($reply->text);

        return $this->record($question, AssistantMode::Generated, $answer, AnswerComposer::sources($answer, $chunks), contact: false, settings: $settings, usage: $usage);
    }

    /**
     * وضع الدليل: أقرب سؤال معتمد أو مقالة بنصها، وأقرب غيرها روابط، بلا مزود.
     *
     * @return Answer
     */
    private function guide(string $question, ?string $path): array
    {
        /** @var Collection<int, AssistantChunk> $chunks */
        $chunks = AssistantRetriever::search($question, 3, [AssistantSource::Faq, AssistantSource::Guide], $path);

        if ($chunks->isEmpty()) {
            return $this->record($question, AssistantMode::NoMatch, __('assistant.no_guide'), [], contact: true);
        }

        $first = $chunks->first();
        $answer = $first->title."\n".$first->body;

        return $this->record($question, AssistantMode::Guide, __('assistant.guide_intro')."\n\n".$answer, AnswerComposer::links($chunks), contact: false);
    }

    /**
     * @param  list<array{title: string, url: string, navigate: bool}>  $sources
     * @param  array{input_tokens?: int, output_tokens?: int, cost?: float}  $usage
     * @return Answer
     */
    private function record(string $question, AssistantMode $mode, string $answer, array $sources, bool $contact, ?AssistantSettings $settings = null, array $usage = []): array
    {
        $log = AssistantLog::query()->create([
            'question' => $question,
            'answer' => $answer,
            'sources' => array_map(fn (array $source): array => ['title' => $source['title'], 'url' => $source['url']], $sources),
            'mode' => $mode,
            'input_tokens' => $usage['input_tokens'] ?? 0,
            'output_tokens' => $usage['output_tokens'] ?? 0,
            'cost' => $usage['cost'] ?? 0,
            'provider' => $settings?->provider?->value,
            'model' => $settings?->model,
        ]);

        return ['id' => $log->uuid, 'mode' => $mode->value, 'answer' => $answer, 'sources' => $sources, 'contact' => $contact];
    }
}
