<?php

use App\Support\ImageSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('removes embedded metadata such as location from uploaded photos', function () {
    Storage::fake('public');

    $jpeg = UploadedFile::fake()->image('photo.jpg', 400, 300)->getContent();
    // مقطع APP1 بعلامة Exif وبيانات موقع وهمية، يُدرج بعد بداية الملف مباشرة
    $payload = "Exif\0\0GPS-LATITUDE-21.4225";
    $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    Storage::disk('public')->put('settings/photo.jpg', substr($jpeg, 0, 2).$segment.substr($jpeg, 2));

    expect(Storage::disk('public')->get('settings/photo.jpg'))->toContain('GPS-LATITUDE');

    ImageSanitizer::strip('public', 'settings/photo.jpg');

    $clean = (string) Storage::disk('public')->get('settings/photo.jpg');
    expect($clean)->not->toContain('GPS-LATITUDE')
        ->and(imagecreatefromstring($clean))->not->toBeFalse();
});

it('keeps transparency in PNG images', function () {
    Storage::fake('public');
    $image = imagecreatetruecolor(4, 4);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
    ob_start();
    imagepng($image);
    Storage::disk('public')->put('settings/logo.png', (string) ob_get_clean());

    ImageSanitizer::strip('public', 'settings/logo.png');

    $clean = imagecreatefromstring((string) Storage::disk('public')->get('settings/logo.png'));
    expect((imagecolorat($clean, 1, 1) >> 24) & 0x7F)->toBe(127);
});

it('turns a phone photo upright before removing its orientation tag', function (int $orientation, int $x, int $y) {
    Storage::fake('public');

    // صورة 40×20 بيضاء، في زاويتها العليا اليسرى مربع أحمر يُتتبع موضعه بعد الإدارة
    $source = imagecreatetruecolor(40, 20);
    imagefill($source, 0, 0, (int) imagecolorallocate($source, 255, 255, 255));
    imagefilledrectangle($source, 0, 0, 7, 7, (int) imagecolorallocate($source, 255, 0, 0));
    ob_start();
    imagejpeg($source, null, 95);
    $jpeg = (string) ob_get_clean();

    // EXIF بوسم اتجاه واحد
    $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1).pack('vvVvv', 0x0112, 3, 1, $orientation, 0).pack('V', 0);
    $payload = "Exif\0\0".$tiff;
    Storage::disk('public')->put('settings/photo.jpg', substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2));

    ImageSanitizer::strip('public', 'settings/photo.jpg');

    $clean = imagecreatefromstring((string) Storage::disk('public')->get('settings/photo.jpg'));
    $color = imagecolorat($clean, $x, $y);

    expect([imagesx($clean), imagesy($clean)])->toBe([20, 40])
        ->and(($color >> 16) & 0xFF)->toBeGreaterThan(200)
        ->and(($color >> 8) & 0xFF)->toBeLessThan(80);
})->with([
    '6: a quarter turn clockwise puts the red corner at the top right' => [6, 16, 3],
    '8: a quarter turn counterclockwise puts it at the bottom left' => [8, 3, 36],
]);

it('refuses files that are not images', function () {
    Storage::fake('public');
    Storage::disk('public')->put('settings/fake.jpg', '<?php echo 1;');

    expect(fn () => ImageSanitizer::strip('public', 'settings/fake.jpg'))->toThrow(RuntimeException::class);
});
