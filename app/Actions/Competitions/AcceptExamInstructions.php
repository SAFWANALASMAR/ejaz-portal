<?php

namespace App\Actions\Competitions;

use App\Enums\ExamStage;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\ExamLock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * الموافقة على تعليمات الاختبار: «قرأت تعليمات الاختبار وأوافق على الالتزام بها». ويُسجَّل وقتها، ثم تُفتح الأسئلة.
 */
class AcceptExamInstructions
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, CompetitionSubmission $submission, array $data): CompetitionSubmission
    {
        Validator::make($data, ['accept' => ['accepted']], attributes: ['accept' => __('competitions.exam.instructions_accept')])->validate();

        return DB::transaction(function () use ($user, $submission): CompetitionSubmission {
            $locked = ExamLock::stage($user, $submission, ExamStage::Instructions);
            $locked->instructions_accepted_at = now();
            $locked->save();

            return $locked;
        });
    }
}
