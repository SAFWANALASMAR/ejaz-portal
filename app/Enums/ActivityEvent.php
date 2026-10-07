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

    // الأمن والدخول
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case LoginLockout = 'login_lockout';
    case Logout = 'logout';
    case PasswordReset = 'password_reset';
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

    public function getLabel(): string
    {
        return __('activity.events.'.$this->value);
    }
}
