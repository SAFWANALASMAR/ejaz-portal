<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\DrawVoidRequestStatus;
use App\Models\CompetitionDrawVoidRequest;
use App\Notifications\DrawVoidRequestClosed;

/**
 * إسقاط طلبات إلغاء السحب التي مضت عليها LAPSE_DAYS بلا قرار، من الأمر اليومي
 * ejaz:lapse-draw-void-requests: يبقى السحب معتمداً، ويُسجَّل السقوط، ويُبلَّغ الطالب.
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
                    $updated = CompetitionDrawVoidRequest::query()
                        ->whereKey($request->id)
                        ->where('status', DrawVoidRequestStatus::Pending)
                        ->update(['status' => DrawVoidRequestStatus::Lapsed, 'decided_at' => now()]);

                    if ($updated !== 1) {
                        continue;
                    }

                    activity('competitions')->performedOn($request->draw)
                        ->event(ActivityEvent::DrawVoidLapsed->value)
                        ->withProperties(['request_reason' => $request->reason, 'requested_by' => $request->requested_by])
                        ->log(ActivityEvent::DrawVoidLapsed->value);

                    if ($request->requester?->is_active) {
                        $request->requester->notify(new DrawVoidRequestClosed($request->draw->competition, DrawVoidRequestStatus::Lapsed));
                    }

                    $lapsed++;
                }
            });

        return $lapsed;
    }
}
