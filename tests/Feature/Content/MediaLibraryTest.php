<?php

use App\Actions\Content\AddMediaAsset;
use App\Actions\Content\SaveMediaAsset;
use App\Enums\RoleName;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\MediaAssets\Pages\EditMediaAsset;
use App\Models\MediaAsset;
use App\Support\SvgSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake(MediaAsset::disk());
    Storage::fake('public');

    $this->dir = sys_get_temp_dir().'/media-'.Str::random(8);
    File::ensureDirectoryExists($this->dir);
    $this->editor = userWithRole(RoleName::ContentEditor);
});

afterEach(fn () => File::deleteDirectory($this->dir));

function mediaFile(string $dir, string $name, string $contents): string
{
    File::put($dir.'/'.$name, $contents);

    return $dir.'/'.$name;
}

function pngOf(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

it('keeps the original privately and publishes a smaller WebP copy', function () {
    $file = mediaFile($this->dir, 'wide.png', pngOf(2400, 1200));

    $asset = app(AddMediaAsset::class)->handle($this->editor, $file, ['slug' => 'wide', 'title' => 'wide', 'alt' => '【ناقص: نص بديل】']);

    $web = (string) Storage::disk('public')->get((string) $asset->web_path);
    [$width, $height] = getimagesizefromstring($web) ?: [0, 0];

    expect(Storage::disk(MediaAsset::disk())->get($asset->original_path))->toBe(pngOf(2400, 1200))
        ->and(Storage::disk('public')->exists($asset->original_path))->toBeFalse()
        ->and((new finfo(FILEINFO_MIME_TYPE))->buffer($web))->toBe('image/webp')
        ->and([$width, $height])->toBe([AddMediaAsset::WEB_MAX_SIDE, 960])
        ->and([$asset->width, $asset->height, $asset->original_name])->toBe([2400, 1200, 'wide.png'])
        ->and($asset->altText())->toBe('【ناقص: نص بديل】');
});

it('refuses an image wider than the side cap before decoding it, though GD could read it', function () {
    // صورة سليمة تُفك بلا مشكلة دون الحد، فلا ينجح الاختبار إلا به
    $file = mediaFile($this->dir, 'wide.png', pngOf(AddMediaAsset::MAX_SIDE + 1, 1));

    expect(fn () => app(AddMediaAsset::class)->handle($this->editor, $file, ['slug' => 'wide', 'title' => 'x']))
        ->toThrow(ValidationException::class);

    expect(MediaAsset::query()->exists())->toBeFalse()->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('cleans scripts, event handlers and outside links from an SVG icon, and keeps it off the public disk', function () {
    $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)">'
        .'<script>alert(2)</script><foreignObject><div>x</div></foreignObject>'
        .'<a href="javascript:alert(3)"><path d="M0 0h1" onclick="alert(4)" style="fill:url(https://evil.example/x)"/></a>'
        .'<use xlink:href="https://evil.example/s.svg#a"/><use href="#ok"/><path id="ok" d="M1 1h1" fill="#2E6898"/></svg>';

    $asset = app(AddMediaAsset::class)->handle($this->editor, mediaFile($this->dir, 'icon.svg', $svg), ['slug' => 'icon', 'title' => 'icon', 'is_decorative' => true]);
    $clean = (string) Storage::disk(MediaAsset::disk())->get($asset->original_path);

    expect($asset->web_path)->toBeNull()
        ->and($asset->mime_type)->toBe('image/svg+xml')
        ->and($asset->altText())->toBe('')
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and($clean)->toContain('fill="#2E6898"')->toContain('href="#ok"')
        ->not->toContain('script')->not->toContain('onload')->not->toContain('onclick')->not->toContain('foreignObject')
        ->not->toContain('javascript')->not->toContain('evil.example')->not->toContain('<a');
});

it('rejects an SVG with a DOCTYPE, a file that is not an image, and a user without the permission', function () {
    expect(fn () => SvgSanitizer::clean('<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg>&x;</svg>'))->toThrow(RuntimeException::class);
    expect(fn () => SvgSanitizer::clean(mb_convert_encoding('<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE svg><svg/>', 'UTF-16LE', 'UTF-8')))->toThrow(RuntimeException::class);
    expect(SvgSanitizer::clean('<svg><style>.a{background:u\\72l(https://evil.example)}</style><path style="fill:u\\72l(x)" d="M0 0"/></svg>'))
        ->not->toContain('evil.example')->not->toContain('style=');

    expect(fn () => app(AddMediaAsset::class)->handle($this->editor, mediaFile($this->dir, 'fake.png', 'not an image'), ['slug' => 'fake', 'title' => 'x']))
        ->toThrow(ValidationException::class);

    expect(fn () => app(AddMediaAsset::class)->handle(userWithRole(RoleName::Secretary), mediaFile($this->dir, 'ok.png', pngOf(4, 4)), ['slug' => 'ok', 'title' => 'x']))
        ->toThrow(AuthorizationException::class);

    expect(MediaAsset::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(Storage::disk(MediaAsset::disk())->allFiles())->toBe([]);
});

it('opens the media library to the editor only, and never offers deletion', function () {
    $asset = app(AddMediaAsset::class)->handle($this->editor, mediaFile($this->dir, 'a.png', pngOf(4, 4)), ['slug' => 'a', 'title' => 'a']);

    $this->actingAs($this->editor)->get(MediaAssetResource::getUrl('index'))->assertOk()->assertSee('a');
    $this->actingAs($this->editor)->get(MediaAssetResource::getUrl('edit', ['record' => $asset]))->assertOk();

    foreach ([RoleName::BoardMember, RoleName::Secretary, RoleName::Member] as $role) {
        $this->actingAs(userWithRole($role))->get(MediaAssetResource::getUrl('index'))->assertForbidden();
    }

    expect($this->editor->can('delete', $asset))->toBeFalse()
        ->and(userWithRole(RoleName::SuperAdmin)->can('delete', $asset))->toBeFalse();
});

it('lets the editor complete the alt text from the panel, logged, and clears it for a decorative image', function () {
    $asset = app(AddMediaAsset::class)->handle($this->editor, mediaFile($this->dir, 'a.png', pngOf(4, 4)), ['slug' => 'a', 'title' => 'a', 'alt' => '【ناقص: نص بديل】']);
    $this->actingAs($this->editor);

    Livewire::test(EditMediaAsset::class, ['record' => $asset->getRouteKey()])
        ->fillForm(['title' => 'قاعة', 'alt' => 'قاعة محاضرات', 'is_decorative' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($asset->refresh()->alt)->toBe('قاعة محاضرات')
        ->and(Activity::whereMorphedTo('subject', $asset)->where('event', 'updated')->sole()->causer?->is($this->editor))->toBeTrue();

    app(SaveMediaAsset::class)->handle($this->editor, $asset, ['title' => 'قاعة', 'alt' => 'نص', 'is_decorative' => true]);

    expect($asset->refresh()->alt)->toBeNull()->and($asset->altText())->toBe('');

    expect(fn () => app(SaveMediaAsset::class)->handle(userWithRole(RoleName::Secretary), $asset, ['title' => 'x', 'is_decorative' => false]))
        ->toThrow(AuthorizationException::class);
});
