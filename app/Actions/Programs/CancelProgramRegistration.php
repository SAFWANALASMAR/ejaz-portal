<?php

namespace App\Actions\Programs;

use App\Enums\RegistrationStatus;
use App\Models\LibraryProgress;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Notifications\ProgramRegistrationChanged;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * إلغاء المستخدم تسجيله قبل بدء البرنامج، أو إلغاء تسجيلات حساب أُخفيت بياناته. المقعد المحرر يذهب لأقدم المنتظرين في المعاملة نفسها
 * (بقفل سطر البرنامج كالتسجيل)، ويُشعَر بالبريد.
 */
class CancelProgramRegistration
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $user, ProgramRegistration $registration): void
    {
        if (! $user->can('cancel', $registration)) {
            throw new AuthorizationException;
        }

        $this->release($registration);
    }

    /**
     * عند إخفاء بيانات حساب: تُلغى تسجيلاته في البرامج التي لم تبدأ، فتتحرر مقاعدها للمنتظرين.
     */
    public function releaseAllOf(User $user): void
    {
        ProgramRegistration::query()
            ->whereBelongsTo($user)
            ->where('status', '!=', RegistrationStatus::Cancelled->value)
            // وبرامج المكتبة المفتوحة التي لم يُتمّها، فتتحرر مقاعدها كذلك
            ->whereHas('program', fn ($program) => $program->where(fn ($open) => $open->where('starts_at', '>', now())
                ->orWhere(fn ($library) => $library->whereNotNull('library_item_id')->where(fn ($end) => $end->whereNull('ends_at')->orWhere('ends_at', '>', now())))))
            ->whereNull('completed_at')
            ->get()
            ->each(fn (ProgramRegistration $registration) => $this->release($registration));
    }

    private function release(ProgramRegistration $registration): void
    {
        DB::transaction(function () use ($registration): void {
            /** @var Program $program */
            $program = Program::withTrashed()->whereKey($registration->program_id)->lockForUpdate()->firstOrFail();

            /** @var ProgramRegistration $locked */
            $locked = ProgramRegistration::query()->whereKey($registration->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === RegistrationStatus::Cancelled) {
                return;
            }

            $freesSeat = $locked->status === RegistrationStatus::Confirmed;
            $locked->update(['status' => RegistrationStatus::Cancelled, 'cancelled_at' => now()]);
            $registration->setRawAttributes($locked->getAttributes(), true);

            if (! $freesSeat || ! $program->has_waitlist || $program->trashed() || ! $program->is_published || ! $program->hasFreeSeat()) {
                return;
            }

            $next = ProgramRegistration::query()
                ->where('program_id', $program->id)
                ->where('status', RegistrationStatus::Waitlisted->value)
                ->orderBy('waitlisted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($next !== null) {
                $next->update(['status' => RegistrationStatus::Confirmed, 'waitlisted_at' => null]);

                // وبرنامج المكتبة يبدأ للمنتظر من ترقيته: لا يُحسب تقدّمه قبلها (مراجعة المشرف على #126)
                if ($program->isLibraryProgram()) {
                    $next->forceFill([
                        'started_at' => now(),
                        'opened_at' => null,
                        'pages_seen' => null,
                        'pages_count' => 0,
                        'seconds_baseline' => (int) LibraryProgress::query()->where('user_id', $next->user_id)->where('library_item_id', $program->library_item_id)->value('seconds_consumed'),
                    ])->save();
                }
                $next->user->notify(new ProgramRegistrationChanged($next, ProgramRegistrationChanged::PROMOTED));
            }
        });
    }
}
