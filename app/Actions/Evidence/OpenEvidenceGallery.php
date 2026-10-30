<?php

namespace App\Actions\Evidence;

use App\Models\EvidenceGallery;
use App\Support\NetworkPrefix;

/**
 * فتح المعرض برابطه السري بلا حساب: المعرض الساري ببنوده ومصادرها ومُصدر رابطه، ويُسجَّل الفتح بوقته وشبكة الزائر
 * المقنّعة (مرة في الدقيقة لكل شبكة، فلا يكثر السجل بالتحديث ومعاينات الروابط). والرمز الخاطئ والمنتهي والملغى
 * سواء: null، فيرد المسار 404 واحدة.
 */
class OpenEvidenceGallery
{
    public function handle(string $token, ?string $ip): ?EvidenceGallery
    {
        $gallery = EvidenceGallery::findActiveByToken($token);

        if ($gallery === null) {
            return null;
        }

        $network = NetworkPrefix::masked($ip);

        if (! $gallery->visits()->where('network', $network)->where('visited_at', '>', now()->subMinute())->exists()) {
            $gallery->visits()->create(['network' => $network, 'visited_at' => now()]);
        }

        return $gallery->load(['issuer', 'program', 'items.album', 'items.archiveItem.category']);
    }
}
