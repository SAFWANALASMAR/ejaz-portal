<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\SubmissionStatus;
use App\Models\Competition;
use App\Models\CompetitionDraw;
use App\Models\CompetitionDrawVoid;
use App\Models\User;
use App\Support\DrawSelector;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * السحب الإلكتروني بعد إغلاق التقديم: المشمولون كل المشاركات المرسلة (غير المحذوفة) بأرقامها المرجعية
 * مرتبة، والبذرة 32 بايتاً من random_int، والفائزون منها (DrawSelector). ويُسجَّل كل ذلك سجلاً ثابتاً.
 * وسحب معتمد واحد لكل مسابقة، فلا يُكرر حتى تأتي نتيجة مرغوبة.
 * ونطاق «المؤهلين» ينتظر تعريفهم من الجمعية (open-questions.md).
 */
class RunDraw
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Competition $competition, int $winnersCount): CompetitionDraw
    {
        if (! $actor->can('competitions.draw')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $competition, $winnersCount): CompetitionDraw {
            // سحبان متزامنان (أو نقرتان) يتسلسلان، والثاني يجد الأول فيُرفض. والفحوص على النسخة المقفولة
            $locked = Competition::query()->withTrashed()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

            if ($locked->trashed() || ! $locked->is_published) {
                throw new AuthorizationException;
            }

            if ($locked->closes_at->isFuture()) {
                throw ValidationException::withMessages(['winners_count' => __('competitions.draws.still_open')]);
            }

            // سحب معتمد واحد لكل مسابقة، ولا جديد إلا بعد إلغاء السابق بسبب (VoidDraw)
            if ($locked->draws()->whereDoesntHave('void')->exists()) {
                throw ValidationException::withMessages(['winners_count' => __('competitions.draws.already_drawn')]);
            }

            // من ألغى آخر سحب لا يعيده بنفسه، فلا يكرر المشرف العام السحب حتى نتيجة بعينها. رفض صريح
            // في الإجراء، لأن المشرف العام ينال competitions.draw عبر Gate::after. وهو رفض إجرائي كبقية رفضات
            // السحب، فتعرضه اللوحة رسالةً، ويبقى رفض الصلاحية نفسه 403
            $lastVoid = CompetitionDrawVoid::query()
                ->whereIn('draw_id', $locked->draws()->select('id'))
                ->latest('voided_at')
                ->latest('id')
                ->first();

            if ($lastVoid?->voided_by === $actor->id) {
                throw ValidationException::withMessages(['winners_count' => __('competitions.draws.voided_by_you')]);
            }

            /** @var list<string> $entrants */
            $entrants = $competition->submissions()
                ->where('status', SubmissionStatus::Submitted)
                ->whereNotNull('reference')
                ->orderBy('reference')
                ->pluck('reference')
                ->all();

            if ($winnersCount < 1 || $winnersCount > count($entrants)) {
                throw ValidationException::withMessages(['winners_count' => __('competitions.draws.count', ['max' => count($entrants)])]);
            }

            $seed = '';

            for ($i = 0; $i < 32; $i++) {
                $seed .= sprintf('%02x', random_int(0, 255));
            }

            $draw = new CompetitionDraw;
            $draw->competition_id = $competition->id;
            $draw->seed = $seed;
            $draw->entrants = $entrants;
            $draw->entrants_hash = DrawSelector::fingerprint($entrants);
            $draw->entrants_count = count($entrants);
            $draw->winners_count = $winnersCount;
            $draw->winners = DrawSelector::winners($seed, $entrants, $winnersCount);
            $draw->executed_by = $actor->id;
            $draw->executed_at = now();
            $draw->save();

            activity('competitions')->performedOn($draw)->causedBy($actor)
                ->event(ActivityEvent::DrawExecuted->value)
                ->withProperties(['competition_id' => $competition->id, 'entrants_hash' => $draw->entrants_hash])
                ->log(ActivityEvent::DrawExecuted->value);

            return $draw;
        });
    }
}
