<?php

namespace App\Actions\Accounts;

use App\Models\CompetitionSubmission;
use App\Models\Payment;
use App\Models\SurveyResponse;
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
            'competition_submissions' => CompetitionSubmission::query()->where('user_id', $user->id)->with('competition')->get()
                ->map(fn (CompetitionSubmission $submission): array => [
                    'competition' => $submission->competition->title,
                    'status' => $submission->status->value,
                    'reference' => $submission->reference,
                    'category' => $submission->category,
                    'region' => $submission->region?->value,
                    'answers' => (array) $submission->answers,
                    'submitted_at' => $date($submission->submitted_at),
                ])->all(),
            'survey_responses' => SurveyResponse::query()->where('user_id', $user->id)->with('survey')->get()
                ->map(fn (SurveyResponse $response): array => [
                    'survey' => $response->survey->title,
                    'answers' => $response->answers,
                    'submitted_at' => $date($response->submitted_at),
                ])->all(),
            'payments' => Payment::query()->where('user_id', $user->id)->orderBy('id')->get()
                ->map(fn (Payment $payment): array => [
                    'purpose' => $payment->purpose->value,
                    'amount' => $payment->formattedAmount(),
                    'currency' => $payment->currency,
                    'status' => $payment->status->value,
                    'receipt_number' => $payment->receipt_number,
                    'paid_at' => $date($payment->paid_at),
                    'created_at' => $date($payment->created_at),
                ])->all(),
        ];
    }
}
