<?php

namespace App\Actions\Programs;

use App\Enums\RegistrationStatus;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Notifications\ProgramRegistrationChanged;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * التسجيل في برنامج (requirements/03). داخل معاملة تقفل سطر البرنامج ثم تعدّ المؤكدين، فلا
 * يقبل تسجيلان متزامنان على آخر مقعد معاً: الثاني ينتظر القفل ثم يرى المقاعد ممتلئة. وعند
 * الامتلاء: قائمة الانتظار إن فُعّلت، وإلا الرفض. والتسجيل الملغى يُعاد تفعيله.
 */
class RegisterForProgram
{
    /**
     * @param  array<string, mixed>  $answers  فُحصت أنواعها بقواعد حقول البرنامج (FormFieldRules)
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Program $program, array $answers): ProgramRegistration
    {
        // حساب نشط موثق البريد فقط، ولو استُدعي الإجراء من غير مسار التسجيل
        if (! $user->is_active || $user->anonymized_at !== null || ! $user->hasVerifiedEmail()) {
            throw new AuthorizationException;
        }

        $answers = collect($answers)->only(array_column($program->fields(), 'key'))->all();

        return DB::transaction(function () use ($user, $program, $answers): ProgramRegistration {
            /** @var Program $locked */
            $locked = Program::query()->whereKey($program->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->registrationIsOpen()) {
                throw ValidationException::withMessages(['registration' => __('programs.errors.closed')]);
            }

            $registration = ProgramRegistration::query()
                ->where('program_id', $locked->id)
                ->where('user_id', $user->id)
                ->first();

            if ($registration !== null && $registration->status !== RegistrationStatus::Cancelled) {
                throw ValidationException::withMessages(['registration' => __('programs.errors.already_registered')]);
            }

            $status = match (true) {
                $locked->hasFreeSeat() => RegistrationStatus::Confirmed,
                $locked->has_waitlist => RegistrationStatus::Waitlisted,
                default => throw ValidationException::withMessages(['registration' => __('programs.errors.full')]),
            };

            $registration ??= new ProgramRegistration;
            $registration->program_id = $locked->id;
            $registration->user_id = $user->id;
            $registration->fill([
                'status' => $status,
                'answers' => $answers === [] ? null : $answers,
                'cancelled_at' => null,
                'reminded_at' => null,
                'attended_at' => null,
                'waitlisted_at' => $status === RegistrationStatus::Waitlisted ? now() : null,
            ])->save();
            $registration->setRelation('program', $locked);

            $user->notify(new ProgramRegistrationChanged($registration, $status === RegistrationStatus::Confirmed
                ? ProgramRegistrationChanged::CONFIRMED
                : ProgramRegistrationChanged::WAITLISTED));

            return $registration;
        });
    }
}
