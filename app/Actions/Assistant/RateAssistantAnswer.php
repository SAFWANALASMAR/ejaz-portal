<?php

namespace App\Actions\Assistant;

use App\Enums\AssistantRating;
use App\Models\AssistantLog;

/**
 * تقييم الزائر لجواب («مفيد» أو «غير مفيد»). يحق له تقييم أجوبته في جلسته وحدها (AssistantController)، ويغيّره.
 */
class RateAssistantAnswer
{
    public function handle(AssistantLog $log, AssistantRating $rating): void
    {
        $log->update(['rating' => $rating]);
    }
}
