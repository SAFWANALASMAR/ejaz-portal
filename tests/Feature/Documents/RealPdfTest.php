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
    // إجراء Rendition ينفّذ /JS بلا الاسم /JavaScript، وكلها صيغ يقرؤها القارئ (مراجعة الطلب 258)
    'rendition script after a comment inside the reference' => ["<< /S /Rendition /JS 12 %c\n0 R >>"],
    'rendition script after a comment with an encoded line break' => ["<< /S /Rendition /JS %#0Dx\n(app.alert(1)) >>"],
    'rendition script with an encoded key and comment' => ["<< /S /Rendition /J#53 %#0Dx\n(app.alert(1)) >>"],
    'rendition script reference without a space before R' => ['<< /S /Rendition /JS 12 0R >>'],
    'rendition script reference with a sign' => ['<< /S /Rendition /JS +12 0 R >>'],
    'rendition script reference with a decimal' => ['<< /S /Rendition /JS 12.0 0 R >>'],
    'xfa after a comment inside the reference' => ["<< /XFA 12 %c\n0 R >>"],
    'xfa after a comment with an encoded line break' => ["<< /XFA %#0Dx\n[(t) 6 0 R] >>"],
    'xfa reference without a space before R' => ['<< /XFA 12 0R >>'],
]);

it('checks long runs of comments after /JS in linear time', function (string $payload) {
    // كان نمط التعليق يعيد المسح لكل /JS، فملف من كيلوبايتين يحبس عامل PHP دقيقة (مراجعة الطلب 258). والهامش واسع: ميلّي ثانيتان الآن
    $started = microtime(true);

    expect(RealPdf::problem(pdfContents(deflatedObject($payload))))->toBe('validation.pdf_active_content')
        ->and(microtime(true) - $started)->toBeLessThan(2.0);
})->with([
    'key then comment, repeated' => [str_repeat('/JS %', 200_000)],
    'comment then key, repeated' => [str_repeat("%/JS\n", 200_000)],
]);
