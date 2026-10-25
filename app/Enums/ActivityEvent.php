<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أحداث سجل العمليات. الحدث الجديد يُضاف هنا مع تسميته في lang/ar/activity.php،
 * فيظهر بالعربية في شاشة السجل وفي خيارات تصفيتها دون استعلام على السجل نفسه.
 */
enum ActivityEvent: string implements HasLabel
{
    // تعديلات النماذج (LogsActivity)
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';

    // الأمن والدخول
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case LoginLockout = 'login_lockout';
    case TwoFactorFailed = 'two_factor_failed';
    case TwoFactorFailuresAlerted = 'two_factor_failures_alerted';
    case TwoFactorRotationRefused = 'two_factor_rotation_refused';
    case Logout = 'logout';
    case PasswordReset = 'password_reset';
    case OtherSessionsEnded = 'other_sessions_ended';
    case Registered = 'registered';
    case AdminCreated = 'admin_created';
    case DataExported = 'data_exported';

    // المستخدمون
    case InvitationSent = 'invitation_sent';
    case RolesChanged = 'roles_changed';
    case PersonalDataChanged = 'personal_data_changed';
    case Anonymized = 'anonymized';

    // المحتوى
    case MenuReordered = 'menu_reordered';
    case ContentReordered = 'content_reordered';

    // المسابقات
    case ResultsApproved = 'results_approved';
    case DrawExecuted = 'draw_executed';
    case DrawVoided = 'draw_voided';
    case DrawVoidRequested = 'draw_void_requested';
    case DrawVoidApproved = 'draw_void_approved';
    case DrawVoidRejected = 'draw_void_rejected';
    case DrawVoidLapsed = 'draw_void_lapsed';
    case SubmissionsDistributed = 'submissions_distributed';
    case SubmissionsExported = 'submissions_exported';
    case PersonalDataPurged = 'personal_data_purged';
    // مسابقة الطلاب (docs/plans/student-competition.md)
    case RoundResultsPublished = 'round_results_published';
    case TieDrawExecuted = 'tie_draw_executed';
    case AttemptsQualified = 'attempts_qualified';
    case CertificatesIssued = 'certificates_issued';
    case NationalIdViewed = 'national_id_viewed';
    case ResultsExported = 'competition_results_exported';

    // قياس الرضا
    case SurveyResultsExported = 'survey_results_exported';

    // المدفوعات
    case PaymentRefunded = 'payment_refunded';
    case PaymentWebhookRejected = 'payment_webhook_rejected';
    case PaymentMismatch = 'payment_mismatch';

    // الجمعية العمومية
    case AttendanceRecorded = 'attendance_recorded';
    case MinutesUploaded = 'minutes_uploaded';
    case MinutesApproved = 'minutes_approved';
    case FileDownloaded = 'file_downloaded';
    case OnlineMeetingFailed = 'online_meeting_failed';
    case OnlineMeetingRetried = 'online_meeting_retried';

    // ربط تيمز (للمشرف العام): بلا السر
    case OnlineMeetingsSettingsUpdated = 'online_meetings_settings_updated';
    case OnlineMeetingsConnectionTested = 'online_meetings_connection_tested';

    // النماذج والأرشيف: كل اطلاع وتنزيل يُسجَّل (requirements/05)
    case Viewed = 'viewed';
    case FormEntryApproved = 'form_entry_approved';
    case FormEntriesExported = 'form_entries_exported';
    // الطلبات
    case InquiryNoteUpdated = 'inquiry_note_updated';
    // البث المباشر
    case LiveStreamArchived = 'live_stream_archived';
    // المساعد الذكي: المفتاح بلا قيمته
    case AssistantKeyChanged = 'assistant_key_changed';
    case AssistantAnswerFlagged = 'assistant_answer_flagged';
    case StatsAnalysisRequested = 'stats_analysis_requested';
    // قنوات التواصل: تغيير مفتاح مزود (بلا قيمته)، ورسالة اختبار من اللوحة، وإشعار تسليم بتوقيع خاطئ
    case MessagingSecretsChanged = 'messaging_secrets_changed';
    case MessagingTestSent = 'messaging_test_sent';
    case MessagingWebhookRejected = 'messaging_webhook_rejected';

    public function getLabel(): string
    {
        return __('activity.events.'.$this->value);
    }
}
