<?php

namespace App\Actions\Documents;

use App\Enums\RoleName;
use App\Models\ArchiveCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ تصنيف في الأرشيف بأدوار من يطّلع على عناصره (المشرف العام وحده — ArchiveCategoryPolicy). والأدوار
 * من الأدوار الإدارية وحدها: الأرشيف داخلي، والمشرف العام يطّلع على الكل دون ذكره.
 */
class SaveArchiveCategory
{
    /**
     * @param  array<string, mixed>  $data  name، وroles
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, ArchiveCategory $category, array $data): ArchiveCategory
    {
        $allowed = $category->exists ? $actor->can('update', $category) : $actor->can('create', ArchiveCategory::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $roles = array_map(fn (RoleName $role): string => $role->value, array_filter(RoleName::panelRoles(), fn (RoleName $role): bool => ! in_array($role, RoleName::overseers(), true)));

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:150', Rule::unique('archive_categories', 'name')->ignore($category->id)],
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::in($roles)],
        ])->validate();

        $category->fill(['name' => $validated['name'], 'roles' => array_values(array_unique($validated['roles']))]);
        $category->save();

        return $category;
    }
}
