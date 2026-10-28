<?php

namespace App\Filament\Resources\Competitions\RelationManagers\Concerns;

use App\Models\Competition;
use App\Models\User;
use App\Support\CompetitionAccess;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * صفحات مسابقة الطلاب في اللوحة تستدعي إجراءاتها، ورفض الإجراء يُعرض رسالةً ويوقف النافذة مفتوحة (halt)
 * بدل خطأ لا يقابل حقلاً فيها. وتظهر في مسابقة الطلاب وحدها.
 */
trait RunsStudentCompetitionActions
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Competition $ownerRecord */
        return $ownerRecord->student_mode && static::canViewStudentPart();
    }

    protected static function canViewStudentPart(): bool
    {
        return auth()->user()?->can('competitions.view') ?? false;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $action
     * @param  (Closure(): mixed)|null  $halt
     * @return T|null
     */
    protected function attempt(Closure $action, ?Closure $halt = null): mixed
    {
        try {
            return $action();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();
        } catch (AuthorizationException $exception) {
            Notification::make()->danger()->title($exception->getMessage() ?: __('competitions.student.admin.not_allowed'))->send();
        }

        if ($halt !== null) {
            $halt();
        }

        return null;
    }

    protected function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    protected function competition(): Competition
    {
        /** @var Competition */
        return $this->getOwnerRecord();
    }

    /**
     * أزرار الكتابة بالربط نفسه كالإجراءات (CompetitionAccess)، فلا تظهر للمشرف العام (اطلاع فقط).
     */
    protected function allows(string $permission): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return CompetitionAccess::allows($user, $permission);
    }

    /**
     * أزرار النشر والسحب والتأهيل والشهادات والتصدير بقاعدة الإجراءات نفسها (CompetitionAccess::managesResults).
     */
    protected function managesResults(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return CompetitionAccess::managesResults($user, $this->competition());
    }

    /**
     * كلمة المرور الحالية للتصدير الكامل برقم الهوية (NationalIdExport يفحصها ويقفل تخمينها).
     */
    protected static function passwordField(): TextInput
    {
        return TextInput::make('password')
            ->label(__('competitions.student.admin.export_full_password'))
            ->password()
            ->required()
            ->autocomplete('current-password');
    }
}
