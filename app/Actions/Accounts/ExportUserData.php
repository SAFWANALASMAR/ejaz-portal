<?php

namespace App\Actions\Accounts;

use App\Models\ServiceRequest;
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
            'service_requests' => ServiceRequest::query()->whereBelongsTo($user)->with('opening')->orderBy('id')->get()
                ->map(fn (ServiceRequest $request): array => [
                    'reference' => $request->reference,
                    'opening' => $request->opening->title,
                    'status' => $request->status->value,
                    'answers' => $request->answers ?? [],
                    'files' => array_map(fn (array $file): string => $file['name'], $request->files ?? []),
                    'submitted_at' => $date($request->submitted_at),
                ])->all(),
        ];
    }
}
