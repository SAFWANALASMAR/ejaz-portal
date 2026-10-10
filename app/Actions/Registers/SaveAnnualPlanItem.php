<?php

namespace App\Actions\Registers;

use App\Models\AnnualPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ بند في الخطة السنوية للبرامج (المدير التنفيذي ومدير المشاريع — AnnualPlanItemPolicy). والبرنامج
 * المنفذ يُربط ببند واحد (وقيد فريد في القاعدة). ويُسجَّل الحفظ.
 */
class SaveAnnualPlanItem
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, AnnualPlanItem $item, array $data): AnnualPlanItem
    {
        if (! ($item->exists ? $actor->can('update', $item) : $actor->can('create', AnnualPlanItem::class))) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'department' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'max:200'],
            'title' => ['required', 'string', 'max:300'],
            'objective_breakdown' => ['nullable', 'string', 'max:5000'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'supporting_entities' => ['nullable', 'string', 'max:2000'],
            'target_group' => ['nullable', 'string', 'max:300'],
            'target_beneficiaries' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'preparation_period' => ['nullable', 'string', 'max:200'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'approvals' => ['nullable', 'string', 'max:2000'],
            'program_id' => [
                'nullable', 'integer', Rule::exists('programs', 'id'),
                Rule::unique('annual_plan_items', 'program_id')->ignore($item->id)->withoutTrashed(),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $item->fill(collect((new AnnualPlanItem)->getFillable())->mapWithKeys(fn (string $field): array => [$field => $validated[$field] ?? null])->all());

        if (! $item->exists) {
            $item->created_by = $actor->id;
        }

        $item->save();

        return $item;
    }
}
