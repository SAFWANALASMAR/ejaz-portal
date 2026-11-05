<?php

namespace App\Actions\Accounts;

use App\Enums\MeetingBody;
use App\Enums\SubmissionStatus;
use App\Enums\SurveyQuestionType;
use App\Models\AssemblyMembership;
use App\Models\BodyMembership;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionSubmission;
use App\Models\FormEntry;
use App\Models\Inquiry;
use App\Models\LibraryItem;
use App\Models\LibraryProgress;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\Payment;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\SubmissionVideo;
use App\Models\SubmissionWork;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

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
        // عناصر «قراءاتي» ومنها المحذوف من اللوحة، باستعلام واحد
        $libraryItems = LibraryItem::withTrashed()->whereIn('id', LibraryProgress::query()->whereBelongsTo($user)->select('library_item_id'))->get()->keyBy('id');

        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $date($user->email_verified_at),
                'phone' => $user->phone,
                'pending_phone' => $user->getAttributes()['pending_phone'] ?? null,
                // رقم أفرغه ترحيل تفرد الجوال من الحساب ويُحفظ للرجوع حتى يُحذف الجدول (البلاغ 185، L1)
                'cleared_phone' => DB::table('phone_login_cleared_phones')->where('user_id', $user->id)->value('phone'),
                'phone_verified_at' => $date($user->phone_verified_at),
                'privacy_accepted_at' => $date($user->privacy_accepted_at),
                'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'roles' => $user->getRoleNames()->all(),
                'created_at' => $date($user->created_at),
                'last_login_at' => $date($user->last_login_at),
                'deletion_requested_at' => $date($user->deletion_requested_at),
                'reading_tracking_disabled_at' => $date($user->reading_tracking_disabled_at),
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
            // «قراءاتي»: تقدّمه في المكتبة، ومنه ما أُلغي نشره (docs/decisions/2026-11-01-my-reading.md)
            'library_progress' => LibraryProgress::query()->whereBelongsTo($user)->orderBy('id')->get()
                ->map(fn (LibraryProgress $progress): array => [
                    'item' => $libraryItems->get($progress->library_item_id)?->title,
                    'section' => $libraryItems->get($progress->library_item_id)?->section->value,
                    'pages_seen' => $progress->pages_seen,
                    'pages_count' => $progress->pages_count,
                    'last_page' => $progress->last_page,
                    'total_pages' => $progress->total_pages,
                    'seconds_consumed' => $progress->seconds_consumed,
                    'last_position' => $progress->last_position,
                    'duration_seconds' => $progress->duration_seconds,
                    'completed_at' => $date($progress->completed_at),
                    'started_at' => $date($progress->created_at),
                    'last_reported_at' => $date($progress->last_reported_at),
                ])->all(),
            'program_registrations' => ProgramRegistration::query()->whereBelongsTo($user)->with('program')->orderBy('id')->get()
                ->map(fn (ProgramRegistration $registration): array => [
                    'program' => $registration->program->title,
                    'status' => $registration->status->value,
                    'answers' => $registration->answers ?? [],
                    'attended_at' => $date($registration->attended_at),
                    // تقدّمه في برامج المكتبة (docs/decisions/2026-11-02-reading-program.md)
                    'started_at' => $date($registration->started_at),
                    'opened_at' => $date($registration->opened_at),
                    'pages_seen' => $registration->pages_seen,
                    'pages_count' => $registration->pages_count,
                    'seconds_baseline' => $registration->seconds_baseline,
                    'completed_at' => $date($registration->completed_at),
                    'cancelled_at' => $date($registration->cancelled_at),
                    'registered_at' => $date($registration->created_at),
                ])->all(),
            'competition_submissions' => CompetitionSubmission::query()->withTrashed()->where('user_id', $user->id)->with(['competition', 'videos.question', 'works', 'attempts.round'])->get()
                ->map(fn (CompetitionSubmission $submission): array => [
                    'competition' => $submission->competition->title,
                    'status' => $submission->status->value,
                    'reference' => $submission->reference,
                    'category' => $submission->category,
                    'region' => $submission->region?->value,
                    // ومسودة الاختبار الإلكتروني بلا إجاباتها: مفاتيح الخيارات بترتيب إدخال المسؤول، فتكشف رمز الخيار المعتم
                    // أثناء الاختبار (المراجعة السحابية، ن٤)
                    'answers' => $submission->competition->exam_mode && $submission->status === SubmissionStatus::Draft ? null : (array) $submission->answers,
                    'submitted_at' => $date($submission->submitted_at),
                    // وجود المقاطع وبياناتها، لا الملف نفسه (يُطلب من الجمعية)
                    'videos' => $submission->videos->map(fn (SubmissionVideo $video): array => [
                        'question' => $video->question->prompt,
                        'status' => $video->status->value,
                        'duration_seconds' => $video->duration_seconds,
                        'size_bytes' => $video->size,
                        'uploaded_at' => $date($video->created_at),
                    ])->all(),
                    // بيانات تسجيل الاختبار الإلكتروني، والأعمال المرفوعة (نوعها ورابطها، لا الملف نفسه)
                    'exam_registration' => [
                        'full_name' => $submission->full_name,
                        'contact_email' => $submission->contact_email,
                        'education_stage' => $submission->education_stage?->value,
                        'national_id' => $submission->national_id,
                        'registered_at' => $date($submission->registered_at),
                        // مسابقة الطلاب
                        'grade' => $submission->grade,
                        'gender' => $submission->gender?->value,
                        'guardian_phone' => $submission->guardian_phone,
                        'extra' => $submission->extra,
                    ],
                    'works' => $submission->works->map(fn (SubmissionWork $work): array => [
                        'type' => $work->type->value,
                        'url' => $work->url,
                        'size_bytes' => $work->size,
                        'uploaded_at' => $date($work->created_at),
                    ])->all(),
                    // محاولات مسابقة الطلاب بإجاباته (مفاتيح الخيارات) بعد التسليم وحده، والدرجة بعد نشر نتائج المرحلة وحدها
                    'attempts' => $submission->attempts->map(fn (CompetitionAttempt $attempt): array => [
                        'round' => $attempt->round->name,
                        'reference' => $attempt->reference,
                        'status' => $attempt->status->value,
                        'started_at' => $date($attempt->started_at),
                        'submitted_at' => $date($attempt->submitted_at),
                        'answers' => $attempt->isInProgress() ? null : $attempt->answers,
                        'score' => $attempt->round->resultsPublished() ? (float) $attempt->score + (float) $attempt->essay_score : null,
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
            'assembly_membership' => self::membership($user, $date),
            // عضوية مجلس الإدارة واللجان، والمكلَّف به من قرارات الاجتماعات (requirements/13 §2)
            'body_memberships' => BodyMembership::query()->withTrashed()->whereBelongsTo($user)->orderBy('starts_on')->get()
                ->map(fn (BodyMembership $membership): array => [
                    'body' => $membership->body->value,
                    'committee' => $membership->committee()->withTrashed()->value('name'),
                    'role' => $membership->role->value,
                    'starts_on' => $membership->starts_on->toDateString(),
                    'ends_on' => $membership->ends_on?->toDateString(),
                ])->all(),
            // حضور اجتماعات المجلس واللجان (وحضور الجمعية العمومية تحت عضويتها)
            'body_meetings_attended' => Meeting::query()->withTrashed()->where('body', '!=', MeetingBody::GeneralAssembly->value)
                ->whereHas('attendees', fn ($query) => $query->whereKey($user->id))
                ->orderBy('starts_at')->get()
                ->map(fn (Meeting $meeting): array => ['meeting' => $meeting->title, 'body' => $meeting->body->value, 'starts_at' => $date($meeting->starts_at)])
                ->all(),
            // البند وموعده وحالته، لا نص القرار (قد يمس غيره، والحد الأدنى من البيانات)، ولا المحذوف
            'assigned_decisions' => MeetingAgendaItem::query()->where('assignee_id', $user->id)->orderBy('id')->get()
                ->map(fn (MeetingAgendaItem $item): array => [
                    'meeting' => $item->meeting()->withTrashed()->value('title'),
                    'item' => $item->title,
                    'due_on' => $item->due_on?->toDateString(),
                    'status' => $item->status->value,
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
            // النماذج المعبأة المحفوظة في ملفه (موظفاً أو مشاركاً)، ومنها المحذوف ناعماً: الإجابات وأسماء المرفقات
            // ووجود التوقيع، لا الملفات نفسها
            'form_entries' => FormEntry::query()->withTrashed()->where('person_id', $user->id)->with('version.template')->orderBy('id')->get()
                ->map(fn (FormEntry $entry): array => [
                    'reference' => $entry->reference,
                    'form' => $entry->version->template->title,
                    'status' => $entry->status->value,
                    'answers' => $entry->answers ?? [],
                    'files' => array_map(fn (array $file): string => $file['name'], $entry->files ?? []),
                    'signed' => ($entry->signatures ?? []) !== [],
                    'created_at' => $date($entry->created_at),
                    'approved_at' => $date($entry->approved_at),
                ])->all(),
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

    /**
     * العضوية في الجمعية العمومية وحضور اجتماعاتها، أو null بلا عضوية.
     *
     * @param  Closure(mixed): ?string  $date
     * @return array<string, mixed>|null
     */
    private static function membership(User $user, Closure $date): ?array
    {
        $membership = AssemblyMembership::query()->whereBelongsTo($user)->first();

        if ($membership === null) {
            return null;
        }

        return [
            'status' => $membership->status->value,
            'joined_on' => $membership->joined_on?->toDateString(),
            'meetings_attended' => Meeting::query()->withTrashed()->generalAssembly()
                ->whereHas('attendees', fn ($query) => $query->whereKey($user->id))
                ->orderBy('starts_at')->get()
                ->map(fn (Meeting $meeting): array => ['meeting' => $meeting->title, 'starts_at' => $date($meeting->starts_at)])
                ->all(),
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
