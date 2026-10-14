<?php

use App\Support\PrivateFileResponse;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/*
| رأس Content-Disposition في تقديم الملفات الخاصة لا يكسره اسم الملف: لا سطر جديد يحقن رأساً،
| ولا اقتباس يغلق الاسم، والاسم العربي يصل في filename* مع اسم احتياطي ASCII (فحص 14 أكتوبر، البند 4).
*/

function dispositionDisk(string $driver): Filesystem
{
    config(['filesystems.disks.disposition-test' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/disposition-test')]]);
    $disk = Storage::fake('disposition-test');
    $disk->put('files/a.pdf', '%PDF-1.4');
    config(['filesystems.disks.disposition-test.driver' => $driver]);

    return $disk;
}

function assertSafeDisposition(string $header): void
{
    expect($header)->not->toContain("\r")->not->toContain("\n")
        ->and($header)->toMatch('/^attachment; filename=(?:[!#$&\'*+.^_`|~0-9A-Za-z-]+|"(?:[\x20-\x21\x23-\x5b\x5d-\x7e]|\\\\.)*")(?:; filename\*=utf-8\'\'[A-Za-z0-9%._~-]+)?$/');
}

it('keeps a newline or a quote in the file name from breaking the header', function () {
    dispositionDisk('local');

    $response = PrivateFileResponse::make('disposition-test', 'files/a.pdf', 'application/pdf', "a\"b\r\nSet-Cookie: x=1.pdf", 5, download: true);
    $header = (string) $response->headers->get('Content-Disposition');

    assertSafeDisposition($header);
    expect($header)->not->toContain('"b')
        ->and($response->headers->has('Set-Cookie'))->toBeFalse();
});

it('sends an Arabic file name in filename* with an ASCII fallback', function () {
    dispositionDisk('local');

    $header = (string) PrivateFileResponse::make('disposition-test', 'files/a.pdf', 'application/pdf', 'تقرير/سنوي.pdf', 5, download: true)
        ->headers->get('Content-Disposition');

    assertSafeDisposition($header);
    expect($header)->toContain("filename*=utf-8''".rawurlencode('تقرير_سنوي.pdf'));
});

it('passes the same safe header to the signed storage link', function () {
    $disk = dispositionDisk('s3');
    $disk->buildTemporaryUrlsUsing(fn (string $path, $expiration, array $options) => 'https://storage.example.test/'.$path.'?d='.rawurlencode($options['ResponseContentDisposition']));

    $location = (string) PrivateFileResponse::make('disposition-test', 'files/a.pdf', 'application/pdf', "x\"\ny.pdf", 5, download: true)
        ->headers->get('Location');

    assertSafeDisposition(rawurldecode((string) str($location)->after('?d=')));
});
