<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\DrawVoidRequestStatus;
use App\Models\CompetitionDraw;
use App\Models\CompetitionDrawVoidRequest;
use App\Notifications\DrawVoidRequestClosed;

/**
 * إسقاط طلبات إلغاء السحب التي مضت عليها LAPSE_DAYS بلا قرار: يبقى السحب معتمداً، ويُسجَّل السقوط،
 * ويُبلَّغ الطالب. من الأمر اليومي ejaz:lapse-draw-void-requests، وعند أي تعامل مع السحب قبله (forDraw)،
 * فلا يبقى طلب منتهٍ معلّقاً حتى الأمر اليومي (مراجعة التكامل، 14 أكتوبر 2026).
 * والتحديث مشروط بأن الطلب ما زال معلّقاً، فلا يسقط طلب اعتُمد أو رُفض في اللحظة نفسها.
 */
class LapseDrawVoidRequests
{
    /**
     * @return int عدد الطلبات الساقطة
     */
    public function handle(): int
    {
        $lapsed = 0;

        CompetitionDrawVoidRequest::query()
            ->where('status', DrawVoidRequestStatus::Pending)
            ->where('requested_at', '<=', now()->subDays(CompetitionDrawVoidRequest::LAPSE_DAYS))
            ->with(['draw.competition', 'requester'])
            ->chunkById(100, function ($requests) use (&$lapsed): void {
                foreach ($requests as $request) {
                    /** @var CompetitionDrawVoidRequest $request */
                    if ($this->lapse($request)) {
                        $lapsed++;
                    }
                }
            });

        return $lapsed;
    }

    /**
     * يُسقط طلب السحب المعلّق إن مضت مدته.
     *
     * @return bool أُسقط الآن
     */
    public function forDraw(CompetitionDraw $draw): bool
    {
        $request = $draw->voidRequest()->with(['draw.competition', 'requester'])->first();

        if ($request === null || ! $request->hasLapsed() || ! $this->lapse($request)) {
            return false;
        }

        $draw->unsetRelation('voidRequest');

        return true;
    }

    private function lapse(CompetitionDrawVoidRequest $request): bool
    {
        $updated = CompetitionDrawVoidRequest::query()
            ->whereKey($request->id)
            ->where('status', DrawVoidRequestStatus::Pending)
            ->update(['status' => DrawVoidRequestStatus::Lapsed, 'decided_at' => now()]);

        if ($updated !== 1) {
            return false;
        }

        activity('competitions')->performedOn($request->draw)
            ->event(ActivityEvent::DrawVoidLapsed->value)
            ->withProperties(['request_reason' => $request->reason, 'requested_by' => $request->requested_by])
            ->log(ActivityEvent::DrawVoidLapsed->value);

        if ($request->requester?->is_active) {
            $request->requester->notify(new DrawVoidRequestClosed($request->draw->competition, DrawVoidRequestStatus::Lapsed));
        }

        return true;
    }
}
