<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/test-error/{code}', fn (string $code) => abort((int) $code))->middleware('web');
});

it('shows every error page in Arabic from the right, with no technical detail and no indexing', function (int $code, string $key) {
    $this->get('/test-error/'.$code)
        ->assertStatus($code)
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee(__($key.'.title'))
        ->assertSee(__($key.'.message'))
        ->assertSee(route('home'), false)
        ->assertDontSee('Not Found')
        ->assertDontSee('Server Error')
        ->assertDontSee('vendor/laravel');
})->with([
    [403, 'errors.403'],
    [404, 'errors.404'],
    [419, 'errors.419'],
    [429, 'errors.429'],
    [500, 'errors.500'],
    [503, 'errors.503'],
    'a client error with no text of its own' => [405, 'errors.client'],
    'a server error with no text of its own' => [502, 'errors.server'],
]);

it('shows the site header and footer on client errors', function () {
    $this->get('/test-error/404')->assertSee(__('site.skip_to_content'))->assertSee('id="main"', false);
});

it('shows server errors without reading the database, so they appear even when it fails', function () {
    DB::enableQueryLog();

    $this->get('/test-error/500')->assertStatus(500);

    expect(DB::getQueryLog())->toBeEmpty();
});
