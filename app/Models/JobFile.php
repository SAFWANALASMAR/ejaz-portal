<?php

namespace App\Models;

use App\Enums\JobFileIcon;
use App\Enums\RoleName;
use Database\Factories\JobFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * ملف وظيفة من «دليل ملفات ومهام الوظائف» (requirements/11): وظيفة مسؤولة عن متابعته واكتماله وتحديثه،
 * ووظائف مشاركة فيه وفق المصفوفة. ومن يملك job_files.view (المدير التنفيذي) يطّلع على كل الملفات.
 *
 * @property RoleName $responsible_role
 * @property list<string> $participant_roles
 * @property JobFileIcon $icon
 */
#[Fillable(['title', 'description', 'responsible_role', 'participant_roles', 'participants_contribute', 'icon', 'sort'])]
class JobFile extends Model
{
    /** @use HasFactory<JobFileFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responsible_role' => RoleName::class,
            'participant_roles' => 'array',
            'participants_contribute' => 'boolean',
            'icon' => JobFileIcon::class,
            'sort' => 'integer',
            'from_matrix' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<JobFileDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(JobFileDocument::class);
    }

    public function isResponsible(User $user): bool
    {
        return $user->hasRole($this->responsible_role->value);
    }

    public function isParticipant(User $user): bool
    {
        return $this->participant_roles !== [] && $user->hasAnyRole($this->participant_roles);
    }

    /**
     * الملفات التي يطّلع عليها الحساب: كلها لمن يملك job_files.view، وإلا ما هو مسؤول عنه أو مشارك فيه.
     *
     * @return Builder<JobFile>
     */
    public static function visibleTo(User $user): Builder
    {
        if ($user->can('job_files.view')) {
            return self::query();
        }

        $roles = $user->getRoleNames()->values()->all();

        // بلا أدوار لا ملف
        return self::query()->where(function (Builder $query) use ($roles): void {
            $query->whereIn('responsible_role', $roles);

            foreach ($roles as $role) {
                $query->orWhereJsonContains('participant_roles', $role);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('job_files')
            ->logOnly(['title', 'description', 'responsible_role', 'participant_roles', 'participants_contribute', 'icon', 'sort'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
