{{--
    إشعار الجمعية برسالة «تواصل معنا» (InquiryReceived): الرقم والنوع ورابط اللوحة وحدها، بلا أي نص
    من المرسل. بياناته وموضوعه ونص رسالته في اللوحة خلف الصلاحية والتحقق الثنائي، فلا تخرج إلى صندوق
    بريد يغيّره إعداد واحد، ولا يحوّل عميل البريد رابطاً كتبه المرسل رابطاً (decisions.md).
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
            @foreach (['reference' => $inquiry->reference, 'type' => $inquiry->type->getLabel()] as $field => $value)
                <tr>
                    <th style="width: 30%; text-align: start; color: #5b6b6b; font-weight: normal; vertical-align: top;">{{ __('inquiries.fields.'.$field) }}</th>
                    <td style="text-align: start;">{{ $value }}</td>
                </tr>
            @endforeach
        </table>

        <p style="color: #5b6b6b;">{{ __('inquiries.mail.received.panel') }}</p>

        <p style="margin-top: 24px;">
            <a href="{{ $url }}" style="display: inline-block; padding: 10px 20px; border-radius: 999px; background: #1f4d4a; color: #ffffff; text-decoration: none;">{{ __('inquiries.mail.received.action') }}</a>
        </p>

        <p style="margin-bottom: 0; color: #5b6b6b;">{{ __('account.mail.salutation') }}</p>
    </div>
</body>
</html>
