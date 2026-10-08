<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Field;

/**
 * حقل توقيع يُرسم بالفأرة أو اللمس، وحالته صورة PNG بترميز base64 (لوحة رسم بـ Alpine الذي مع Filament،
 * بلا مكتبة). ويفحصها SaveFormEntry ويعيد ترميزها قبل حفظها (SignatureImage).
 */
class SignaturePad extends Field
{
    protected string $view = 'filament.forms.signature-pad';
}
