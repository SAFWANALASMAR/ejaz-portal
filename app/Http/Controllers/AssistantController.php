<?php

namespace App\Http\Controllers;

use App\Actions\Assistant\AskAssistant;
use App\Actions\Assistant\RateAssistantAnswer;
use App\Enums\AssistantRating;
use App\Http\Requests\AskAssistantRequest;
use App\Models\AssistantLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * نافذة المساعد في الموقع (resources/js/assistant.js): السؤال وتقييم الجواب، بردود JSON. المساران خلف
 * EnsureAssistantEnabled وحدودهما (throttle:assistant).
 *
 * أرقام أجوبة الزائر في جلسته وحدها، فلا يقيّم غيرها، ولا يدخل السجلَّ شيءٌ من الجلسة.
 */
class AssistantController extends Controller
{
    private const SESSION_KEY = 'assistant.answers';

    private const REMEMBERED = 30;

    public function ask(AskAssistantRequest $request, AskAssistant $action): JsonResponse
    {
        $answer = $action->handle($request->string('question')->toString(), $request->string('page')->toString() ?: null);

        $ids = array_slice([...(array) $request->session()->get(self::SESSION_KEY, []), $answer['id']], -self::REMEMBERED);
        $request->session()->put(self::SESSION_KEY, $ids);

        return response()->json($answer);
    }

    public function rate(Request $request, string $answer, RateAssistantAnswer $action): JsonResponse
    {
        $validated = $request->validate(['rating' => ['required', Rule::enum(AssistantRating::class)]]);

        abort_unless(in_array($answer, (array) $request->session()->get(self::SESSION_KEY, []), true), 404);

        $action->handle(AssistantLog::query()->where('uuid', $answer)->firstOrFail(), AssistantRating::from($validated['rating']));

        return response()->json(['ok' => true]);
    }
}
