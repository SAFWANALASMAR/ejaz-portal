<?php

use App\Models\ArchiveItem;
use App\Support\PrivateUpload;
use App\Support\SvgSanitizer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
| الرفع (الاختبار الأمني 0.14.0): ما يقبل «PDF أو صورة» في القرص الخاص (الأرشيف والسجلات وملفات الوظائف — PrivateUpload)
| يحكم بالمحتوى لا بالاسم، فيُرفض SVG بسكربت وHTML وPHP بأي امتداد، ويُحذف ملفها. والصورة يُعاد ترميزها فتسقط الحمولة
| الملحقة بها. واسم التنزيل يأخذ امتداده من النوع المفحوص. وما يُرفع صوراً للعامة أو للمسابقات يغطيه FinalAuditTest،
| والـ PDF المزيف أو بسكربت في الطلبات ServicesTest، وSVG مكتبة الوسائط MediaLibraryTest. ولا استيراد ZIP في البوابة.
*/

beforeEach(function () {
    Storage::fake('local');
});

function archiveUpload(string $contents): string
{
    $path = ArchiveItem::DIRECTORY.'/'.Str::random(40).'.bin';
    Storage::disk(ArchiveItem::disk())->put($path, $contents);

    return $path;
}

it('refuses by content anything that is not a PDF or an image, whatever its name, and deletes it', function (string $contents) {
    $path = archiveUpload($contents);

    expect(fn () => PrivateUpload::check(ArchiveItem::disk(), ArchiveItem::DIRECTORY, $path, 'files', 'invalid'))->toThrow(ValidationException::class)
        ->and(Storage::disk(ArchiveItem::disk())->exists($path))->toBeFalse();
})->with([
    'svg with a script' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script></svg>',
    'html' => '<!doctype html><html><body><script>alert(document.cookie)</script></body></html>',
    'php' => '<?php system($_GET["c"]); ?>',
    'html after a fake PDF name' => "<html><script>alert(1)</script></html>\n%PDF-1.4",
    'empty' => '',
]);

it('re-encodes an image so a payload appended to it is gone', function () {
    $path = archiveUpload(imageBytes('jpg', 20, 20).'<?php system($_GET["c"]); ?><script>alert(1)</script>');

    $checked = PrivateUpload::check(ArchiveItem::disk(), ArchiveItem::DIRECTORY, $path, 'files', 'invalid');
    $stored = (string) Storage::disk(ArchiveItem::disk())->get($path);

    expect($checked['extension'])->toBe('jpg')
        ->and($stored)->not->toContain('<?php')
        ->and($stored)->not->toContain('<script>');
});

it('refuses a file over the limit before reading it', function () {
    $path = archiveUpload(pdfContents(str_repeat('0', 21 * 1024 * 1024)));

    expect(fn () => PrivateUpload::check(ArchiveItem::disk(), ArchiveItem::DIRECTORY, $path, 'files', 'invalid'))->toThrow(ValidationException::class);
});

it('refuses a path outside the expected directory, without deleting it', function () {
    Storage::disk(ArchiveItem::disk())->put('registers/other.pdf', pdfContents());

    foreach (['registers/other.pdf', ArchiveItem::DIRECTORY.'/../registers/other.pdf'] as $path) {
        expect(fn () => PrivateUpload::check(ArchiveItem::disk(), ArchiveItem::DIRECTORY, $path, 'files', 'invalid'))->toThrow(ValidationException::class);
    }

    expect(Storage::disk(ArchiveItem::disk())->exists('registers/other.pdf'))->toBeTrue();
});

// كان اسم التنزيل يحمل امتداد صاحبه، فتُنزَّل صورة باسم x.html
it('names the download by the checked type, not the extension the uploader gave', function (string $original, string $extension, string $expected) {
    expect(PrivateUpload::downloadName($original, $extension))->toBe($expected);
})->with([
    'html name on an image' => ['صورة.html', 'png', 'صورة.png'],
    'double extension' => ['report.php.pdf', 'pdf', 'report.php.pdf'],
    'svg name on a pdf' => ['logo.svg', 'pdf', 'logo.pdf'],
    'path in the name' => ['../../etc/passwd', 'pdf', '..-..-etc-passwd.pdf'],
    'no name' => ['', 'jpg', 'document.jpg'],
]);

it('cleans every script vector out of an imported SVG', function () {
    $clean = SvgSanitizer::clean('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)">'
        .'<script>alert(1)</script><a xlink:href="javascript:alert(1)"><rect width="1" height="1" onclick="alert(1)"/></a>'
        .'<foreignObject><iframe src="https://evil.example"></iframe></foreignObject><use href="https://evil.example/x.svg#a"/>'
        .'<style>@import url(https://evil.example)</style></svg>');

    expect($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('onload')
        ->and($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('javascript:')
        ->and($clean)->not->toContain('foreignObject')
        ->and($clean)->not->toContain('evil.example')
        ->and($clean)->toStartWith('<svg');
});
