<?php

use App\Rules\RealPdf;

// /JS و/XFA مصادفةً في بيانات الصور والخطوط الثنائية لا تجعل الكتاب «محتوى نشطاً»، والمفتاحان بقيمتيهما يُرفضان في كل صورة
// (docs/decisions/2026-11-13-fix-real-pdf-false-positives.md)

function deflatedObject(string $content): string
{
    return "4 0 obj\n<< /Filter /FlateDecode >>\nstream\n".gzcompress($content)."\nendstream\nendobj";
}

it('does not mistake /JS or /XFA bytes inside image or font data for active content', function () {
    // كما وُجد في كتب الجمعية: البايتات الثلاثة بعدها بايت لا يبدأ قيمة، في صورة JPEG خام وفي تدفق مضغوط
    $binary = "\x9f\x10/JS\x01\xfe\x88/JS|\x07/XFA\x00\xff/JS)";

    expect(RealPdf::problem(pdfContents("4 0 obj\n<< /Filter /DCTDecode >>\nstream\n{$binary}\nendstream\nendobj")))->toBeNull()
        ->and(RealPdf::problem(pdfContents(deflatedObject($binary))))->toBeNull();
});

it('still refuses the /JS and /XFA keys with their values, raw or compressed', function (string $object) {
    expect(RealPdf::problem(pdfContents($object)))->toBe('validation.pdf_active_content')
        ->and(RealPdf::problem(pdfContents(deflatedObject($object))))->toBe('validation.pdf_active_content');
})->with([
    'script as a string' => ['<< /JS (app.alert(1)) >>'],
    'script as a hex string' => ['<< /JS <6170702e616c657274283129> >>'],
    'script in another object' => ['<< /JS 5 0 R >>'],
    'script after a comment' => ["<< /JS % note\n(app.alert(1)) >>"],
    'hex-encoded key' => ['<< /J#53 (app.alert(1)) >>'],
    'key right after another name' => ['<< /Type/JS(app.alert(1)) >>'],
    'xfa array' => ['<< /XFA [(template) 6 0 R] >>'],
    'xfa in another object' => ['<< /XFA 6 0 R >>'],
    'javascript action type alone' => ['<< /S /JavaScript >>'],
]);
