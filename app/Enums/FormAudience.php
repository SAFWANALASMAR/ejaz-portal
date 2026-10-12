<?php

namespace App\Enums;

use App\Models\User;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;

/**
 * جمهور النموذج الإلكتروني: يحدد من يُربط به المعبأ («حفظ النماذج تلقائيًا في ملفات الموظفين أو
 * المشاركين» — requirements/12 البند 10). والمشارك يرى المعتمد من نماذجه في لوحته.
 */
enum FormAudience: string implements HasLabel
{
    case Staff = 'staff';
    case Participants = 'participants';

    public function getLabel(): string
    {
        return __('documents.audiences.'.$this->value);
    }

    /**
     * من يصح أن يكون صاحب المعبأ: حساب مفعّل غير مخفي البيانات، له دور موظف في نماذج الموظفين، وبلا دور
     * موظف ولا دور إداري في نماذج المشاركين. يستعمله الاختيار في اللوحة والتحقق في SaveFormEntry.
     *
     * @return Builder<User>
     */
    public function people(): Builder
    {
        $query = User::query()->where('is_active', true)->whereNull('anonymized_at');

        return $this === self::Staff
            ? $query->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', RoleName::values(RoleName::staff())))
            : $query->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', RoleName::values([...RoleName::staff(), ...RoleName::panelRoles()])));
    }
}
