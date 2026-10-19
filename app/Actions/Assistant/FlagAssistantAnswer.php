<?php

namespace App\Actions\Assistant;

use App\Enums\ActivityEvent;
use App\Models\AssistantLog;
use App\Models\User;
use App\Rules\PlainText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * تعليم إجابة المساعد خاطئة بملاحظة، لتصحيح المحتوى أو إضافة سؤال شائع (requirements/10)، أو إلغاء التعليم.
 * يُسجَّل في سجل العمليات بلا نص السؤال.
 */
class FlagAssistantAnswer
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, AssistantLog $log, bool $flagged, ?string $note = null): void
    {
        if (! $actor->can('update', $log)) {
            throw new AuthorizationException;
        }

        $note = Validator::make(['note' => $note], ['note' => ['nullable', 'string', 'max:1000', new PlainText]])->validate()['note'] ?? null;

        DB::transaction(function () use ($actor, $log, $flagged, $note): void {
            $log->update(['is_flagged' => $flagged, 'flag_note' => $flagged ? $note : null]);

            activity('assistant')
                ->causedBy($actor)
                ->performedOn($log)
                ->event(ActivityEvent::AssistantAnswerFlagged->value)
                ->withProperties(['flagged' => $flagged])
                ->log(ActivityEvent::AssistantAnswerFlagged->value);
        });
    }
}
