<?php

namespace App\Actions\Assistant;

use App\Enums\AssistantReviewStatus;
use App\Models\AssistantFaq;
use App\Models\User;
use App\Rules\PlainText;
use App\Support\Assistant\GuideRoutes;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ سؤال شائع للمساعد: نص عادي، ومصدره مسار صفحة قائمة في البوابة. والحفظ يحدّث فهرس المساعد (AssistantIndex).
 */
class SaveAssistantFaq
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, AssistantFaq $faq, array $data): AssistantFaq
    {
        if (! ($faq->exists ? $actor->can('update', $faq) : $actor->can('create', AssistantFaq::class))) {
            throw new AuthorizationException;
        }

        $data['source_url'] = is_string($data['source_url'] ?? null) ? trim($data['source_url']) : null;

        $validated = Validator::make($data, [
            'question' => ['required', 'string', 'max:300', new PlainText],
            'answer' => ['required', 'string', 'max:3000', new PlainText(multiline: true)],
            'keywords' => ['nullable', 'string', 'max:500', new PlainText],
            'source_url' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                if (! GuideRoutes::isSitePath((string) $value)) {
                    $fail(__('assistant.admin.faqs.source_invalid'));
                }
            }],
            'status' => ['required', Rule::enum(AssistantReviewStatus::class)->only([AssistantReviewStatus::Draft, AssistantReviewStatus::Approved])],
            'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], attributes: [
            'question' => __('assistant.admin.faqs.question'),
            'answer' => __('assistant.admin.faqs.answer'),
            'keywords' => __('assistant.admin.faqs.keywords'),
            'source_url' => __('assistant.admin.faqs.source_url'),
        ])->validate();

        $faq->fill([...$validated, 'sort' => (int) ($validated['sort'] ?? 0)]);
        DB::transaction(fn () => $faq->save());

        return $faq;
    }
}
