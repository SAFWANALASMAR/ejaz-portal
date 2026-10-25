<?php

namespace App\Models;

use App\Enums\RoleName;
use Database\Factories\ArchiveCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * تصنيف في الأرشيف بأدوار من يطّلع على عناصره (requirements/05: صلاحيات اطلاع على مستوى التصنيف).
 * والمشرف العام يطّلع على كل التصنيفات.
 *
 * @property list<string> $roles
 */
#[Fillable(['name', 'roles'])]
class ArchiveCategory extends Model
{
    /** @use HasFactory<ArchiveCategoryFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roles' => 'array',
        ];
    }

    /**
     * @return HasMany<ArchiveItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ArchiveItem::class);
    }

    public function allows(User $user): bool
    {
        return RoleName::isOverseer($user) || $user->hasAnyRole($this->roles);
    }

    /**
     * التصنيفات التي يطّلع عليها الحساب.
     *
     * @return Builder<ArchiveCategory>
     */
    public static function visibleTo(User $user): Builder
    {
        if (RoleName::isOverseer($user)) {
            return self::query();
        }

        $roles = $user->getRoleNames()->values()->all();

        // بلا أدوار لا تصنيف
        return self::query()->where(function (Builder $query) use ($roles): void {
            $query->whereIn('id', []);

            foreach ($roles as $role) {
                $query->orWhereJsonContains('roles', $role);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('documents')->logOnly(['name', 'roles'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
