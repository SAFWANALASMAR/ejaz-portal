<?php

namespace App\Actions\Assistant;

use App\Enums\AssistantReviewStatus;
use App\Models\AssistantGuide;
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
 * حفظ مقالة في «دليل استخدام البوابة»: عنوان، وخطوات مرقمة نصاً عادياً، وصفحة عامة قائمة. والحفظ يحدّث الفهرس.
 */
class SaveAssistantGuide
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, AssistantGuide $guide, array $data): AssistantGuide
    {
        if (! ($guide->exists ? $actor->can('update', $guide) : $actor->can('create', AssistantGuide::class))) {
            throw new AuthorizationException;
        }

        $data['steps'] = array_values(array_filter(array_map(fn ($step): string => is_string($step) ? trim($step) : '', (array) ($data['steps'] ?? [])), fn (string $step): bool => $step !== ''));

        $validated = Validator::make($data, [
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(AssistantGuide::class)->ignore($guide)],
            'title' => ['required', 'string', 'max:200', new PlainText],
            'keywords' => ['nullable', 'string', 'max:500', new PlainText],
            'steps' => ['required', 'array', 'min:1', 'max:12'],
            'steps.*' => ['string', 'max:500', new PlainText],
            'route_name' => ['required', 'string', 'max:150', function (string $attribute, mixed $value, Closure $fail): void {
                if (! GuideRoutes::allows((string) $value)) {
                    $fail(__('assistant.admin.guides.route_invalid'));
                }
            }],
            'status' => ['required', Rule::enum(AssistantReviewStatus::class)],
            'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], attributes: [
            'slug' => __('assistant.admin.guides.slug'),
            'title' => __('assistant.admin.guides.title'),
            'keywords' => __('assistant.admin.guides.keywords'),
            'steps' => __('assistant.admin.guides.steps'),
            'route_name' => __('assistant.admin.guides.route_name'),
        ])->validate();

        $guide->fill([...$validated, 'sort' => (int) ($validated['sort'] ?? 0)]);
        DB::transaction(fn () => $guide->save());

        return $guide;
    }
}
