<?php

namespace App\Actions\Accounts;

use App\Models\Inquiry;
use App\Models\User;

/**
 * نسخة من بيانات المستخدم بصيغة قابلة للقراءة (حق الاطلاع في نظام حماية البيانات).
 * كل وحدة تضيف قسمها هنا عند بنائها (الطلبات، والتسجيلات، والمشاركات...).
 */
class ExportUserData
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $date = fn ($value): ?string => $value?->toIso8601String();

        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $date($user->email_verified_at),
                'phone' => $user->phone,
                'phone_verified_at' => $date($user->phone_verified_at),
                'privacy_accepted_at' => $date($user->privacy_accepted_at),
                'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'roles' => $user->getRoleNames()->all(),
                'created_at' => $date($user->created_at),
                'last_login_at' => $date($user->last_login_at),
                'deletion_requested_at' => $date($user->deletion_requested_at),
            ],
            // رسائل حسابه، ورسائل بريده الموثّق قبل أن يسجّل
            'inquiries' => Inquiry::withTrashed()
                ->where(fn ($query) => $query->whereBelongsTo($user)->when(
                    $user->email_verified_at !== null,
                    fn ($query) => $query->orWhereRaw('lower(email) = ?', [mb_strtolower($user->email)]),
                ))
                ->orderBy('id')
                ->get()
                ->map(fn (Inquiry $inquiry): array => [
                    'reference' => $inquiry->reference,
                    'type' => $inquiry->type->value,
                    'subject' => $inquiry->subject,
                    'message' => $inquiry->message,
                    'status' => $inquiry->status->value,
                    'sent_at' => $date($inquiry->created_at),
                ])->all(),
        ];
    }
}
