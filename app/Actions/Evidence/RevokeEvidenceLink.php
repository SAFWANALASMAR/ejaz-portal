<?php

namespace App\Actions\Evidence;

use App\Enums\ActivityEvent;
use App\Models\EvidenceGallery;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * إلغاء رابط المعرض فوراً: الصفحة وروابط ملفاتها الموقّعة ترد 404 من الطلب التالي. ولا يعود إلا بإصدار رابط جديد.
 */
class RevokeEvidenceLink
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, EvidenceGallery $gallery): void
    {
        if (! $actor->can('revoke', $gallery)) {
            throw new AuthorizationException;
        }

        $gallery->forceFill(['revoked_at' => now()])->save();

        activity('documents')->causedBy($actor)->performedOn($gallery)
            ->event(ActivityEvent::EvidenceLinkRevoked->value)
            ->log(ActivityEvent::EvidenceLinkRevoked->value);
    }
}
