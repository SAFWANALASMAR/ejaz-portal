<?php

namespace App\Console\Commands;

use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use App\Notifications\ProgramRegistrationChanged;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * تذكير المسجلين المؤكدين قبل البرنامج بيوم (requirements/03). يعمل كل ساعة، ولا يكرر التذكير
 * (reminded_at)، ولا يذكّر ببرنامج غير منشور أو محذوف.
 */
class SendProgramReminders extends Command
{
    protected $signature = 'programs:send-reminders';

    protected $description = 'Remind confirmed registrants of programs starting within a day';

    public function handle(): int
    {
        $sent = 0;

        ProgramRegistration::query()
            ->where('status', RegistrationStatus::Confirmed->value)
            ->whereNull('reminded_at')
            ->whereHas('program', fn (Builder $program) => $program
                ->withoutTrashed()
                ->where('is_published', true)
                ->whereBetween('starts_at', [now(), now()->addDay()]))
            ->whereHas('user', fn (Builder $user) => $user->where('is_active', true)->whereNull('anonymized_at'))
            ->with(['program', 'user'])
            ->chunkById(500, function ($registrations) use (&$sent): void {
                foreach ($registrations as $registration) {
                    $registration->user->notify(new ProgramRegistrationChanged($registration, ProgramRegistrationChanged::REMINDER));
                    $registration->forceFill(['reminded_at' => now()])->saveQuietly();
                    $sent++;
                }
            });

        $this->info((string) $sent);

        return self::SUCCESS;
    }
}
