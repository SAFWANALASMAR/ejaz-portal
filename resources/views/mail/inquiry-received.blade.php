{{--
    إشعار الجمعية برسالة «تواصل معنا» (InquiryReceived). نص المرسل مهرّب بلا Markdown، فلا تصير
    روابطه روابط هنا، وأسطره محفوظة. وأنماط البريد مضمّنة لأن عملاء البريد لا يقرؤون ملفات الأنماط.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ __('inquiries.mail.received.subject', ['reference' => $inquiry->reference]) }}</title>
</head>
<body style="margin: 0; padding: 24px; background: #f6f3ee; font-family: Tahoma, Arial, sans-serif; color: #1f2a2a; line-height: 1.8;">
    <div style="max-width: 640px; margin: 0 auto; padding: 24px; background: #ffffff; border-radius: 12px;">
        <p style="margin-top: 0;">{{ __('inquiries.mail.received.line') }}</p>

        <table cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse;">
            @foreach ([
                'reference' => $inquiry->reference,
                'type' => $inquiry->type->getLabel(),
                'subject' => $inquiry->subject,
                'name' => $inquiry->name,
                'email' => $inquiry->email,
                'phone' => $inquiry->phone,
            ] as $field => $value)
                @if (filled($value))
                    <tr>
                        <th style="width: 30%; text-align: start; color: #5b6b6b; font-weight: normal; vertical-align: top;">{{ __('inquiries.fields.'.$field) }}</th>
                        <td style="text-align: start;" @if ($field === 'email' || $field === 'phone') dir="ltr" @endif>{{ $value }}</td>
                    </tr>
                @endif
            @endforeach
        </table>

        <p style="margin-bottom: 4px; color: #5b6b6b;">{{ __('inquiries.fields.message') }}</p>
        <div style="white-space: pre-line; padding: 12px 16px; border: 1px solid #e3ddd2; border-radius: 8px;">{{ $inquiry->message }}</div>

        <p style="margin-top: 24px;">
            <a href="{{ $url }}" style="display: inline-block; padding: 10px 20px; border-radius: 999px; background: #1f4d4a; color: #ffffff; text-decoration: none;">{{ __('inquiries.mail.received.action') }}</a>
        </p>

        <p style="margin-bottom: 0; color: #5b6b6b;">{{ __('account.mail.salutation') }}</p>
    </div>
</body>
</html>
