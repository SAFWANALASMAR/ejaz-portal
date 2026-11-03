<?php

namespace App\Actions\Evidence;

use App\Enums\ActivityEvent;
use App\Models\EvidenceGallery;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * إصدار رابط المعرض السري أو تجديده: رمز عشوائي من 48 حرفاً ورقماً (Str::random على random_bytes)، تُحفظ بصمته
 * sha256 وحدها ويُعاد الرابط إلى مصدره مرة واحدة، ومعها المُصدر: لا يُعرض من الأرشيف إلا ما يطّلع عليه هو.
 * والتجديد يبطل الرابط السابق وروابط ملفاته فوراً، ويرفع الإلغاء. ويُسجَّل الإصدار بلا الرمز.
 */
class IssueEvidenceLink
{
    /**
     * @return string الرابط كاملاً، ولا يُعرض بعدها
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, EvidenceGallery $gallery, mixed $expiresAt): string
    {
        // والتعديل يشترط اطلاع المُصدر على كل عنصر أرشيف في المعرض (EvidenceGalleryPolicy)، ولا رابط لمعرض محذوف
        if ($gallery->trashed() || ! $actor->can('update', $gallery)) {
            throw new AuthorizationException;
        }

        // انتهاء إلزامي لا يتجاوز سنة من الإصدار، فلا يبقى رابط سري صالحاً بلا حد إن تسرّب (المراجعة الأمنية بعد v0.14.2)
        $validated = Validator::make(['expires_at' => $expiresAt], [
            'expires_at' => ['required', 'date', 'after:now', 'before_or_equal:'.now()->addDays(EvidenceGallery::MAX_LINK_DAYS)->toDateTimeString()],
        ], attributes: ['expires_at' => __('evidence.fields.expires_at')])->validate();

        $token = Str::random(EvidenceGallery::TOKEN_LENGTH);
        $expires = Carbon::parse($validated['expires_at']);

        $gallery->forceFill([
            'token_hash' => EvidenceGallery::hashToken($token),
            'token_issued_at' => now(),
            'token_issued_by' => $actor->id,
            'expires_at' => $expires,
            'revoked_at' => null,
        ])->save();

        activity('documents')->causedBy($actor)->performedOn($gallery)
            ->event(ActivityEvent::EvidenceLinkIssued->value)
            ->withProperties(['expires_at' => $expires->toIso8601String()])
            ->log(ActivityEvent::EvidenceLinkIssued->value);

        return route('evidence.show', ['token' => $token]);
    }
}
