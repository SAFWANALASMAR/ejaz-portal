<?php

use App\Support\ShareImage;

it('uses the page image when it is a share image format', function (string $image) {
    expect(ShareImage::url($image))->toBe(url($image));
})->with([
    'absolute png' => 'http://localhost/storage/news/cover.png',
    'relative jpeg' => '/storage/programs/cover.JPEG',
    'webp with query' => 'https://cdn.example.com/a.webp?v=2',
]);

it('falls back to the site default as an absolute url', function (?string $image) {
    expect(ShareImage::url($image))->toBe(asset(ShareImage::DEFAULT))
        ->and(ShareImage::url($image))->toStartWith('http');
})->with([
    'no image' => null,
    'svg' => '/storage/pages/drawing.svg',
    'empty' => '',
]);

it('points the default at an image that exists', function () {
    expect(public_path(ShareImage::DEFAULT))->toBeFile();
});

it('keeps the square logo for structured data and a 1200x630 default for sharing', function () {
    [$width, $height] = getimagesize(public_path(ShareImage::DEFAULT));

    expect(public_path(ShareImage::LOGO))->toBeFile()
        ->and([$width, $height])->toBe([1200, 630]);
});
