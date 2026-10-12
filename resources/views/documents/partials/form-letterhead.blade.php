{{--
    ترويسة النموذج المطبوع: الشعار الرسمي واسم الجمعية وبيانات التواصل من الإعدادات (FormEntryLayout::letterhead).
    وما لم يُضبط يظهر علامة الناقص، والترويسة المعتمدة لم تصل بعد (open-questions.md). جداول لا flex، لأن
    mPDF لا يفهمه. $logo مسار على القرص لـ mPDF، ورابط في المتصفح.
--}}
<table class="letterhead" role="presentation">
    <tr>
        <td class="letterhead-org">
            <div class="org-name">{{ $letterhead['name'] }}</div>
            @if ($letterhead['contact'] === [])
                <div class="org-contact missing">{{ __('documents.print.missing_contact') }}</div>
            @else
                <div class="org-contact">{{ implode(' — ', $letterhead['contact']) }}</div>
            @endif
        </td>
        <td class="letterhead-logo">
            <img src="{{ $logo }}" alt="{{ $letterhead['name'] }}" class="logo">
        </td>
    </tr>
</table>
