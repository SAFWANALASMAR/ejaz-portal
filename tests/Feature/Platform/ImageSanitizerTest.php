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

it('refuses files that are not images', function () {
    Storage::fake('public');
    Storage::disk('public')->put('settings/fake.jpg', '<?php echo 1;');

    expect(fn () => ImageSanitizer::strip('public', 'settings/fake.jpg'))->toThrow(RuntimeException::class);
});
