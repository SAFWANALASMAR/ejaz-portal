<?php

use App\Jobs\FetchYoutubeThumbnail;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
| غلاف يوتيوب بأعلى دقة ثم hqdefault (درس الموقع القديم — الدفعة الثانية من التحسين البصري).
*/

it('takes the maximum resolution cover when YouTube has one', function () {
    Http::fake([
        'https://i.ytimg.com/vi/abcdefghijk/maxresdefault.jpg' => Http::response(imageBytes('jpeg', 64, 36), 200, ['Content-Type' => 'image/jpeg']),
        'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg' => Http::response(imageBytes('jpeg', 32, 18), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $jpeg = FetchYoutubeThumbnail::download('abcdefghijk');

    expect(getimagesizefromstring((string) $jpeg)[0])->toBe(64);
    Http::assertSentCount(1);
});

it('falls back to hqdefault when the video has no maximum resolution cover', function () {
    Http::fake([
        'https://i.ytimg.com/vi/abcdefghijk/maxresdefault.jpg' => Http::response('', 404),
        'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg' => Http::response(imageBytes('jpeg', 32, 18), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    expect(getimagesizefromstring((string) FetchYoutubeThumbnail::download('abcdefghijk'))[0])->toBe(32);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/hqdefault.jpg'));
});

it('returns nothing when neither size is an image', function () {
    Http::fake(['*' => Http::response('', 404)]);

    expect(FetchYoutubeThumbnail::download('abcdefghijk'))->toBeNull();
});
