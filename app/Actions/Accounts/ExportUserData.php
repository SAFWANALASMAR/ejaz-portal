<?php

namespace App\Actions\Accounts;

use App\Enums\SurveyQuestionType;
use App\Models\CompetitionSubmission;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\SubmissionVideo;
use App\Models\SurveyQuestion;
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
            // والمحذوف من اللوحة داخل: بياناته باقية حتى مدة الاحتفاظ، فيشملها حق الاطلاع
            'service_requests' => ServiceRequest::query()->withTrashed()->whereBelongsTo($user)->with('opening')->orderBy('id')->get()
                ->map(fn (ServiceRequest $request): array => [
                    'reference' => $request->reference,
                    'opening' => $request->opening->title,
                    'status' => $request->status->value,
                    'answers' => $request->answers ?? [],
                    'files' => array_map(fn (array $file): string => $file['name'], $request->files ?? []),
                    'submitted_at' => $date($request->submitted_at),
                ])->all(),
            'program_registrations' => ProgramRegistration::query()->whereBelongsTo($user)->with('program')->orderBy('id')->get()
                ->map(fn (ProgramRegistration $registration): array => [
                    'program' => $registration->program->title,
                    'status' => $registration->status->value,
                    'answers' => $registration->answers ?? [],
                    'attended_at' => $date($registration->attended_at),
                    'cancelled_at' => $date($registration->cancelled_at),
                    'registered_at' => $date($registration->created_at),
                ])->all(),
            'competition_submissions' => CompetitionSubmission::query()->withTrashed()->where('user_id', $user->id)->with(['competition', 'videos.question'])->get()
                ->map(fn (CompetitionSubmission $submission): array => [
                    'competition' => $submission->competition->title,
                    'status' => $submission->status->value,
                    'reference' => $submission->reference,
                    'category' => $submission->category,
                    'region' => $submission->region?->value,
                    'answers' => (array) $submission->answers,
                    'submitted_at' => $date($submission->submitted_at),
                    // وجود المقاطع وبياناتها، لا الملف نفسه (يُطلب من الجمعية)
                    'videos' => $submission->videos->map(fn (SubmissionVideo $video): array => [
                        'question' => $video->question->prompt,
                        'status' => $video->status->value,
                        'duration_seconds' => $video->duration_seconds,
                        'size_bytes' => $video->size,
                        'uploaded_at' => $date($video->created_at),
                    ])->all(),
                ])->all(),
            'survey_responses' => SurveyResponse::query()->where('user_id', $user->id)->with('survey.questions')->get()
                ->map(fn (SurveyResponse $response): array => [
                    'survey' => $response->survey->title,
                    // نص السؤال ونص الخيار، لا المعرّفات، فتُقرأ النسخة بلا الرجوع إلى البوابة
                    'answers' => $response->survey->questions
                        ->filter(fn (SurveyQuestion $question): bool => array_key_exists((string) $question->id, (array) $response->answers))
                        ->map(fn (SurveyQuestion $question): array => [
                            'question' => $question->prompt,
                            'answer' => self::answer($question, ((array) $response->answers)[(string) $question->id]),
                        ])->values()->all(),
                    'submitted_at' => $date($response->submitted_at),
                ])->all(),
        ];
    }

    /**
     * الإجابة كما رآها المستجيب: الخيار بنصه، والمقياس رقمه، والنص كما كتبه.
     */
    private static function answer(SurveyQuestion $question, mixed $value): mixed
    {
        return $question->type === SurveyQuestionType::Choice && is_int($value) ? ($question->optionList()[$value] ?? $value) : $value;
    }
}
