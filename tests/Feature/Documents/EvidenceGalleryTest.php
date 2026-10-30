<?php

use App\Actions\Evidence\IssueEvidenceLink;
use App\Actions\Evidence\RevokeEvidenceLink;
use App\Actions\Evidence\SaveEvidenceGallery;
use App\Enums\ArchiveItemType;
use App\Enums\RoleName;
use App\Filament\Resources\EvidenceGalleries\EvidenceGalleryResource;
use App\Filament\Resources\EvidenceGalleries\Pages\CreateEvidenceGallery;
use App\Filament\Resources\EvidenceGalleries\Pages\EditEvidenceGallery;
use App\Models\Album;
use App\Models\ArchiveCategory;
use App\Models\ArchiveItem;
use App\Models\EvidenceGallery;
use App\Models\EvidenceGalleryItem;
use App\Models\EvidenceGalleryVisit;
use App\Support\NetworkPrefix;
use App\Support\SpaLink;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| معرض الشواهد برابط سري (قرار صفوان، 30 أكتوبر 2026): صور الألبومات وملفات الأرشيف الموجودة لمانح أو مسؤول بلا حساب،
| برمز تُحفظ بصمته وحدها، وانتهاء وإلغاء وتجديد، وسجل فتح بالشبكة المقنّعة. وكل رفض 404 واحدة، ولا فهرسة ولا Referer.
*/

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->secretary = userWithRole(RoleName::Secretary);

    Storage::disk('public')->put('albums/one.webp', imageBytes('webp'));
    Storage::disk('public')->put('albums/two.webp', imageBytes('webp'));
    $this->album = Album::factory()->create(['title' => 'ألبوم الحفل', 'photos' => ['albums/one.webp', 'albums/two.webp']]);

    $this->photo = storedPrivateFile(ArchiveItem::DIRECTORY, imageBytes('png'), 'png');
    $this->pdf = storedPrivateFile(ArchiveItem::DIRECTORY);
    $this->archive = ArchiveItem::factory()->create([
        'type' => ArchiveItemType::Evidence,
        'title' => 'شاهد البرنامج',
        'files' => [
            ['path' => $this->photo, 'name' => 'صورة.png', 'size' => 100],
            ['path' => $this->pdf, 'name' => 'تقرير.pdf', 'size' => 200],
        ],
    ]);
});

function evidenceData(array $overrides = []): array
{
    return ['title' => 'شواهد البرنامج للمانح', 'description' => 'وصف تجريبي', 'items' => [], ...$overrides];
}

function evidenceGalleryWith(object $test, array $items): EvidenceGallery
{
    return app(SaveEvidenceGallery::class)->handle($test->secretary, new EvidenceGallery, evidenceData(['items' => $items]));
}

function evidenceToken(string $url): string
{
    return Str::afterLast($url, '/');
}

/**
 * الرد بلا ما يتغير من طلب إلى طلب (رمز CSRF وnonce، والرابط المطلوب نفسه في canonical)، لمقارنة ردود الرفض.
 */
function evidenceNormalized(string $html): string
{
    return (string) preg_replace(['/content="[A-Za-z0-9]{40}"/', '/nonce="[^"]*"/', '#/evidence/[^"]*#'], ['content=""', 'nonce=""', '/evidence/'], $html);
}

it('builds a gallery from albums and archive items in order, issues a link shown once, and opens it without an account', function () {
    $this->actingAs($this->secretary);

    Livewire::test(CreateEvidenceGallery::class)
        ->fillForm([
            'title' => 'شواهد البرنامج للمانح',
            'items' => [
                ['source' => 'archive', 'archive_item_id' => $this->archive->id, 'paths' => []],
                ['source' => 'album', 'album_id' => $this->album->id, 'paths' => ['albums/two.webp']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $gallery = EvidenceGallery::sole();
    expect($gallery->items->pluck('position', 'archive_item_id')->all())->toBe([$this->archive->id => 0, null => 1])
        ->and($gallery->items[1]->paths)->toBe(['albums/two.webp']);

    Livewire::test(EditEvidenceGallery::class, ['record' => $gallery->id])
        ->callAction('issueLink', ['expires_at' => null])
        ->assertNotified(__('evidence.actions.issued'));

    $gallery->refresh();
    expect($gallery->token_hash)->toHaveLength(64)
        ->and(Activity::where('event', 'evidence_link_issued')->whereMorphedTo('subject', $gallery)->exists())->toBeTrue();

    // الرمز لا يُحفظ: يُصدر رابط جديد للزائر (والسابق يبطل)
    $url = app(IssueEvidenceLink::class)->handle($this->secretary, $gallery);
    $token = evidenceToken($url);
    expect(strlen($token))->toBe(EvidenceGallery::TOKEN_LENGTH)
        ->and($gallery->fresh()->token_hash)->toBe(hash('sha256', $token))
        ->and(EvidenceGallery::query()->where('token_hash', $token)->exists())->toBeFalse();

    auth()->logout();

    $response = $this->get($url)->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('<meta name="referrer" content="no-referrer">', false)
        ->assertSee('شواهد البرنامج للمانح')
        ->assertSee(Storage::disk('public')->url('albums/two.webp'), false)
        ->assertDontSee(Storage::disk('public')->url('albums/one.webp'), false)
        ->assertSee('تقرير.pdf')
        ->assertSee('data-gallery', false)
        ->assertDontSee($this->photo, false)
        ->assertDontSee($this->pdf, false);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    // الصورة من الأرشيف أولاً (ترتيب البنود) ثم صورة الألبوم
    preg_match_all('#<img src="([^"]+)"#', $response->getContent(), $matches);
    $sources = array_values(array_unique(array_filter(array_map('html_entity_decode', $matches[1]), fn (string $src): bool => str_contains($src, '/evidence/') || str_contains($src, 'albums/'))));
    expect($sources[0])->toContain('/evidence/files/')
        ->and($sources[1])->toBe(Storage::disk('public')->url('albums/two.webp'));

    $visit = EvidenceGalleryVisit::sole();
    expect($visit->network)->toBe('127.0.0.0/24')
        ->and($visit->visited_at)->not->toBeNull();

    // ملفات الأرشيف بروابط موقّعة تعمل بعد فحص الرمز: الصورة تُعرض، والـ PDF يُنزَّل
    $image = $this->get($sources[0])->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($image->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow, noarchive');

    preg_match('#href="([^"]+/evidence/files/[^"]+)"#', $response->getContent(), $pdf);
    $download = $this->get(html_entity_decode($pdf[1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($download->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('answers a wrong, expired or revoked link with the same 404', function () {
    $gallery = evidenceGalleryWith($this, [['source' => 'album', 'album_id' => $this->album->id]]);
    $url = app(IssueEvidenceLink::class)->handle($this->secretary, $gallery);
    $this->get($url)->assertOk();

    $wrong = $this->get(route('evidence.show', ['token' => Str::random(EvidenceGallery::TOKEN_LENGTH)]))->assertNotFound();
    $malformed = $this->get('/evidence/'.str_repeat('a', 10))->assertNotFound();

    $this->travel(1)->minutes();
    $expiring = evidenceGalleryWith($this, []);
    $expiringUrl = app(IssueEvidenceLink::class)->handle($this->secretary, $expiring, now()->addHour());
    $this->get($expiringUrl)->assertOk();
    $this->travel(2)->hours();
    $expired = $this->get($expiringUrl)->assertNotFound();

    app(RevokeEvidenceLink::class)->handle($this->secretary, $gallery);
    $revoked = $this->get($url)->assertNotFound();

    foreach ([$wrong, $malformed, $expired, $revoked] as $response) {
        expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow, noarchive')
            ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
            ->and(evidenceNormalized($response->getContent()))->toBe(evidenceNormalized($wrong->getContent()));
    }

    // ولا يُسجَّل فتح مرفوض
    expect(EvidenceGalleryVisit::count())->toBe(2)
        ->and(Activity::where('event', 'evidence_link_revoked')->whereMorphedTo('subject', $gallery)->exists())->toBeTrue();
});

it('kills the old link and its file links when the link is regenerated, revoked, or the gallery deleted', function () {
    $gallery = evidenceGalleryWith($this, [['source' => 'archive', 'archive_item_id' => $this->archive->id]]);
    $old = app(IssueEvidenceLink::class)->handle($this->secretary, $gallery);
    preg_match('#<img src="([^"]+/evidence/files/[^"]+)"#', $this->get($old)->getContent(), $match);
    $file = html_entity_decode($match[1]);
    $this->get($file)->assertOk();

    $new = app(IssueEvidenceLink::class)->handle($this->secretary, $gallery->fresh());
    $this->get($old)->assertNotFound();
    $this->get($file)->assertNotFound();
    $this->get($new)->assertOk();

    $gallery->fresh()->delete();
    $this->get($new)->assertNotFound();
});

it('refuses a file link without a valid signature, after it expires, for another item, or once revoked', function () {
    $gallery = evidenceGalleryWith($this, [['source' => 'archive', 'archive_item_id' => $this->archive->id, 'paths' => [$this->pdf]]]);
    $url = app(IssueEvidenceLink::class)->handle($this->secretary, $gallery);
    $this->get($url)->assertOk();
    $item = $gallery->items()->sole();
    $key = EvidenceGalleryItem::fileKey($this->pdf);
    $params = ['item' => $item->id, 'key' => $key, 'link' => $gallery->fresh()->linkKey()];

    $signed = URL::temporarySignedRoute('evidence.file', now()->addMinutes(30), $params);
    $this->get($signed)->assertOk();

    // بلا توقيع، وبتوقيع مبدَّل، وبمفتاح إصدار آخر
    $this->get(route('evidence.file', $params))->assertNotFound();
    $this->get(str_replace('signature=', 'signature=0', $signed))->assertNotFound();
    $this->get(URL::temporarySignedRoute('evidence.file', now()->addMinutes(30), [...$params, 'link' => str_repeat('0', 16)]))->assertNotFound();

    // ملف في العنصر لم يُختر في البند
    $this->get(URL::temporarySignedRoute('evidence.file', now()->addMinutes(30), [...$params, 'key' => EvidenceGalleryItem::fileKey($this->photo)]))->assertNotFound();

    // بعد انتهاء مدته
    $this->travel(31)->minutes();
    $this->get($signed)->assertNotFound();
    $this->travelBack();

    $fresh = URL::temporarySignedRoute('evidence.file', now()->addMinutes(30), $params);
    app(RevokeEvidenceLink::class)->handle($this->secretary, $gallery->fresh());
    $this->get($fresh)->assertNotFound();
});

it('drops a photo or file that left its source, and an archive item deleted from the archive', function () {
    $gallery = evidenceGalleryWith($this, [
        ['source' => 'album', 'album_id' => $this->album->id],
        ['source' => 'archive', 'archive_item_id' => $this->archive->id],
    ]);
    $url = app(IssueEvidenceLink::class)->handle($this->secretary, $gallery);

    $this->album->update(['photos' => ['albums/two.webp']]);
    $this->archive->delete();

    $this->get($url)->assertOk()
        ->assertDontSee(Storage::disk('public')->url('albums/one.webp'), false)
        ->assertSee(Storage::disk('public')->url('albums/two.webp'), false)
        ->assertDontSee('تقرير.pdf')
        ->assertDontSee('/evidence/files/', false);
});

it('rejects an archive item the editor may not see and a path that is not in its source', function () {
    $secret = ArchiveItem::factory()->create(['type' => ArchiveItemType::Evidence, 'archive_category_id' => ArchiveCategory::factory()->roles(['board_member'])->create()->id]);
    $letter = ArchiveItem::factory()->create(['type' => ArchiveItemType::Incoming]);
    $draft = Album::factory()->unpublished()->create();

    expect(fn () => evidenceGalleryWith($this, [['source' => 'archive', 'archive_item_id' => $secret->id]]))->toThrow(ValidationException::class)
        ->and(fn () => evidenceGalleryWith($this, [['source' => 'album', 'album_id' => $this->album->id, 'paths' => ['albums/other.webp']]]))->toThrow(ValidationException::class)
        ->and(fn () => evidenceGalleryWith($this, [['source' => 'archive', 'archive_item_id' => $this->archive->id, 'paths' => ['archive/elsewhere.pdf']]]))->toThrow(ValidationException::class)
        ->and(fn () => evidenceGalleryWith($this, [['source' => 'album', 'album_id' => 999999]]))->toThrow(ValidationException::class)
        // المراسلات والوثائق ليست شواهد، والألبوم غير المنشور لمن لا يطّلع على مكتبة الصور
        ->and(fn () => evidenceGalleryWith($this, [['source' => 'archive', 'archive_item_id' => $letter->id]]))->toThrow(ValidationException::class)
        ->and(fn () => evidenceGalleryWith($this, [['source' => 'album', 'album_id' => $draft->id]]))->toThrow(ValidationException::class)
        ->and(fn () => app(IssueEvidenceLink::class)->handle($this->secretary, evidenceGalleryWith($this, []), now()->subDay()))->toThrow(ValidationException::class)
        ->and(EvidenceGallery::count())->toBe(1);

    $admin = userWithRole(RoleName::TechnicalAdmin);
    expect(app(SaveEvidenceGallery::class)->handle($admin, new EvidenceGallery, evidenceData(['items' => [['source' => 'album', 'album_id' => $draft->id]]]))->items)->toHaveCount(1);
});

it('keeps the screens and the link actions from users without the permission', function () {
    $gallery = evidenceGalleryWith($this, []);

    foreach ([RoleName::ContentEditor, RoleName::ProgramManager, RoleName::Member] as $role) {
        $user = userWithRole($role);
        $this->actingAs($user);
        $this->get(EvidenceGalleryResource::getUrl('index'))->assertForbidden();
        $this->get(EvidenceGalleryResource::getUrl('create'))->assertForbidden();
        $this->get(EvidenceGalleryResource::getUrl('edit', ['record' => $gallery]))->assertForbidden();

        expect(fn () => app(IssueEvidenceLink::class)->handle($user, $gallery))->toThrow(AuthorizationException::class)
            ->and(fn () => app(RevokeEvidenceLink::class)->handle($user, $gallery))->toThrow(AuthorizationException::class)
            ->and(fn () => app(SaveEvidenceGallery::class)->handle($user, new EvidenceGallery, evidenceData()))->toThrow(AuthorizationException::class);
    }

    $this->actingAs($this->secretary)->get(EvidenceGalleryResource::getUrl('edit', ['record' => $gallery]))->assertOk();
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector))->get(EvidenceGalleryResource::getUrl('index'))->assertOk();
    expect($gallery->fresh()->token_hash)->toBeNull();
});

it('limits opening attempts per visitor, wrong tokens included', function () {
    config(['security.evidence_per_minute' => 3]);
    RateLimiter::clear('evidence:'.NetworkPrefix::subscriber('127.0.0.1'));

    foreach (range(1, 3) as $attempt) {
        $this->get(route('evidence.show', ['token' => Str::random(EvidenceGallery::TOKEN_LENGTH)]))->assertNotFound();
    }

    $this->get(route('evidence.show', ['token' => Str::random(EvidenceGallery::TOKEN_LENGTH)]))
        ->assertTooManyRequests()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

it('stays out of the sitemap and robots, and loads as a full page without fast navigation', function () {
    config(['app.indexable' => true]);
    $gallery = evidenceGalleryWith($this, []);
    $url = app(IssueEvidenceLink::class)->handle($this->secretary, $gallery);

    $this->get('/sitemap.xml')->assertOk()->assertDontSee('/evidence', false);
    // لا يُذكر في robots: المنع هناك يحجب وسم noindex عن الزاحف (SitemapController::robots)
    $this->get('/robots.txt')->assertOk()->assertDontSee('evidence', false);
    expect(SpaLink::allows($url))->toBeFalse();
});

it('masks the visitor address to its network', function () {
    expect(NetworkPrefix::masked('203.0.113.77'))->toBe('203.0.113.0/24')
        ->and(NetworkPrefix::masked('2001:db8:abcd:12:1:2:3:4'))->toBe('2001:db8:abcd::/48')
        ->and(NetworkPrefix::masked('::ffff:198.51.100.9'))->toBe('198.51.100.0/24')
        ->and(NetworkPrefix::masked('nonsense'))->toBe('unknown');
});

it('keeps a gallery holding archive items of a hidden category away from an editor who cannot see them, except to revoke it', function () {
    $director = userWithRole(RoleName::ExecutiveDirector);
    $boardOnly = ArchiveItem::factory()->create([
        'type' => ArchiveItemType::Evidence,
        'archive_category_id' => ArchiveCategory::factory()->roles(['board_member'])->create()->id,
        'files' => [['path' => $this->pdf, 'name' => 'سري.pdf', 'size' => 200]],
    ]);
    $gallery = app(SaveEvidenceGallery::class)->handle($director, new EvidenceGallery, evidenceData(['items' => [['source' => 'archive', 'archive_item_id' => $boardOnly->id]]]));
    app(IssueEvidenceLink::class)->handle($director, $gallery);

    $this->actingAs($this->secretary)->get(EvidenceGalleryResource::getUrl('edit', ['record' => $gallery]))->assertForbidden();
    expect(fn () => app(IssueEvidenceLink::class)->handle($this->secretary, $gallery->fresh()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveEvidenceGallery::class)->handle($this->secretary, $gallery->fresh(), evidenceData()))->toThrow(AuthorizationException::class);

    app(RevokeEvidenceLink::class)->handle($this->secretary, $gallery->fresh());
    expect($gallery->fresh()->revoked_at)->not->toBeNull();
});

it('shows only what the issuer may still see, and stops when the issuer is deactivated', function () {
    $gallery = evidenceGalleryWith($this, [
        ['source' => 'album', 'album_id' => $this->album->id],
        ['source' => 'archive', 'archive_item_id' => $this->archive->id],
    ]);
    $url = app(IssueEvidenceLink::class)->handle($this->secretary, $gallery);
    preg_match('#<img src="([^"]+/evidence/files/[^"]+)"#', $this->get($url)->assertOk()->assertSee('تقرير.pdf')->getContent(), $match);
    $file = html_entity_decode($match[1]);

    // نُقل الشاهد إلى تصنيف لا يراه مُصدر الرابط
    $this->archive->update(['archive_category_id' => ArchiveCategory::factory()->roles(['board_member'])->create()->id]);
    $this->get($url)->assertOk()->assertDontSee('تقرير.pdf')->assertSee(Storage::disk('public')->url('albums/one.webp'), false);
    $this->get($file)->assertNotFound();

    $this->secretary->forceFill(['is_active' => false])->save();
    $this->get($url)->assertNotFound();
});

it('does not bring the old link back when a deleted gallery is restored', function () {
    $gallery = evidenceGalleryWith($this, []);
    $url = app(IssueEvidenceLink::class)->handle($this->secretary, $gallery);

    $gallery->delete();
    $gallery->restore();

    $this->get($url)->assertNotFound();
    expect(EvidenceGalleryResource::status($gallery->fresh()))->toBe('revoked')
        ->and(fn () => app(IssueEvidenceLink::class)->handle($this->secretary, tap($gallery->fresh())->delete()))->toThrow(AuthorizationException::class);
});

it('keeps item rows and their file links on a save that does not change them, and logs item changes', function () {
    $this->actingAs($this->secretary);
    $gallery = evidenceGalleryWith($this, [
        ['source' => 'archive', 'archive_item_id' => $this->archive->id, 'paths' => [$this->photo]],
        ['source' => 'album', 'album_id' => $this->album->id],
    ]);
    $ids = $gallery->items()->pluck('id')->all();
    expect(Activity::where('event', 'evidence_items_updated')->whereMorphedTo('subject', $gallery)->sole()->properties['attributes'])
        ->toBe([['source' => 'archive:'.$this->archive->id, 'selected' => 1], ['source' => 'album:'.$this->album->id, 'selected' => null]]);

    // فتح التعديل والحفظ بلا تغيير: البنود نفسها بمعرّفاتها وما اختير منها، ولا سجل جديد للبنود
    Livewire::test(EditEvidenceGallery::class, ['record' => $gallery->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($gallery->items()->pluck('id')->all())->toBe($ids)
        ->and($gallery->items()->first()->paths)->toBe([$this->photo])
        ->and(Activity::where('event', 'evidence_items_updated')->whereMorphedTo('subject', $gallery)->count())->toBe(1);

    // إعادة الترتيب تبقي الصفوف وتُسجَّل
    app(SaveEvidenceGallery::class)->handle($this->secretary, $gallery->fresh(), evidenceData(['items' => [
        ['source' => 'album', 'album_id' => $this->album->id],
        ['source' => 'archive', 'archive_item_id' => $this->archive->id, 'paths' => [$this->photo]],
    ]]));
    expect($gallery->items()->pluck('id')->all())->toBe(array_reverse($ids))
        ->and(Activity::where('event', 'evidence_items_updated')->whereMorphedTo('subject', $gallery)->count())->toBe(2);
});

it('opens and saves a gallery whose sources changed, dropping what is gone without widening a selection', function () {
    $this->actingAs($this->secretary);
    $gallery = evidenceGalleryWith($this, [
        ['source' => 'album', 'album_id' => $this->album->id, 'paths' => ['albums/one.webp']],
        ['source' => 'archive', 'archive_item_id' => $this->archive->id],
    ]);

    $this->album->update(['photos' => ['albums/two.webp']]);
    $this->archive->delete();

    Livewire::test(EditEvidenceGallery::class, ['record' => $gallery->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($gallery->items()->count())->toBe(0);
});

it('stores the expiry picked in Riyadh time as UTC, and revokes from the panel', function () {
    $this->actingAs($this->secretary);
    $gallery = evidenceGalleryWith($this, []);

    Livewire::test(EditEvidenceGallery::class, ['record' => $gallery->id])
        ->callAction('issueLink', ['expires_at' => now()->addYear()->format('Y').'-11-05 10:00:00'])
        ->assertHasNoActionErrors();

    expect($gallery->fresh()->expires_at->utc()->format('H:i'))->toBe('07:00');

    Livewire::test(EditEvidenceGallery::class, ['record' => $gallery->id])
        ->callAction('revokeLink')
        ->assertNotified(__('evidence.actions.revoked'));

    expect($gallery->fresh()->revoked_at)->not->toBeNull()
        ->and(Activity::where('event', 'evidence_link_revoked')->whereMorphedTo('subject', $gallery)->exists())->toBeTrue();
});

it('refuses to revoke without the revoke permission, even with update', function () {
    $gallery = evidenceGalleryWith($this, []);
    app(IssueEvidenceLink::class)->handle($this->secretary, $gallery);

    $editor = userWithRole(RoleName::ContentEditor);
    $editor->givePermissionTo(['evidence_galleries.view', 'evidence_galleries.update']);

    expect(fn () => app(RevokeEvidenceLink::class)->handle($editor, $gallery->fresh()))->toThrow(AuthorizationException::class);
    $this->actingAs($editor);
    Livewire::test(EditEvidenceGallery::class, ['record' => $gallery->id])->assertActionHidden('revokeLink');
});

it('records one visit per network a minute', function () {
    $url = app(IssueEvidenceLink::class)->handle($this->secretary, evidenceGalleryWith($this, []));

    $this->get($url)->assertOk();
    $this->get($url)->assertOk();
    $this->travel(2)->minutes();
    $this->get($url)->assertOk();

    expect(EvidenceGalleryVisit::count())->toBe(2);
});
