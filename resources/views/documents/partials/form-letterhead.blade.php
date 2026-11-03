{{--
    ترويسة النموذج المطبوع: الشعار الرسمي واسم الجمعية وبيانات التواصل من الإعدادات (FormEntryLayout::letterhead).
    وما لم يُضبط يظهر علامة الناقص، والترويسة المعتمدة لم تصل بعد (open-questions.md). جداول لا flex، لأن
    mPDF لا يفهمه. $logo مسار على القرص لـ mPDF، ورابط في المتصفح.
--}}
<table class="letterhead" role="presentation">
    <tr>
        <td class="letterhead-org">
            <div class="org-name">{{ $letterhead['name'] }}</div>
            <div class="org-contact">{{ __('site.license') }}</div>
            @if ($letterhead['contact'] === [])
                {{-- شرط x-missing نفسه: لا علامة ناقص في الإنتاج، فلا تُطبع على نموذج حقيقي --}}
                @unless (app()->isProduction())
                    <div class="org-contact missing">{{ __('documents.print.missing_contact') }}</div>
                @endunless
            @else
                {{-- الجوال والبريد من اليسار، فلا تنقلب أرقامهما ورموزهما داخل السطر العربي --}}
                <div class="org-contact">
                    @foreach ($letterhead['contact'] as $key => $value)
                        @unless ($loop->first) — @endunless
                        @if ($key === 'address')
                            {{ $value }}
                        @else
                            <span dir="ltr">{{ $value }}</span>
                        @endif
                    @endforeach
                </div>
            @endif
        </td>
        <td class="letterhead-logo">
            <img src="{{ $logo }}" alt="{{ $letterhead['name'] }}" class="logo">
        </td>
    </tr>
</table>
