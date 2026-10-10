<?php

use App\Models\Album;
use App\Models\Document;
use App\Models\NewsArticle;

/**
 * الطابع الحديث (ملاحظة المدير التنفيذي): معرض صور بشبكة وعارض مكبَّر، وبطاقات ملفات حديثة.
 * docs/requirements/12-executive-director-2026-10-10.md (القسم 2)
 */
it('shows an album as a gallery grid that opens each photo in an accessible viewer', function () {
    Album::factory()->create(['slug' => 'trip', 'title' => 'رحلة', 'photos' => ['albums/a.jpg', 'albums/b.jpg', 'albums/c.jpg']]);

    $html = (string) $this->get('/media/albums/trip')->assertOk()->getContent();

    expect(substr_count($html, 'data-gallery-open'))->toBe(3)
        ->and($html)->toContain('class="gallery-grid')
        ->toContain('<dialog')
        ->toContain('data-gallery-dialog')
        ->toContain('aria-label="'.e(__('site.gallery.viewer')).'"')
        ->toContain(e(__('site.gallery.close')))
        ->toContain(e(__('site.gallery.next')))
        ->toContain(e(__('site.gallery.previous')))
        ->toContain('/storage/albums/b.jpg');
});

it('uses the same gallery for a news article', function () {
    NewsArticle::factory()->create(['slug' => 'visit', 'gallery' => ['news/1.jpg', 'news/2.jpg']]);

    $html = (string) $this->get('/media/news/visit')->assertOk()->getContent();

    expect(substr_count($html, 'data-gallery-open'))->toBe(2)
        ->and($html)->toContain('data-gallery-dialog');
});

it('lists governance documents as file cards with their type, size, preview and download', function () {
    Document::factory()->create(['slug' => 'bylaws', 'title' => 'اللائحة', 'file_size' => 2 * 1048576]);
    Document::factory()->create(['slug' => 'policy', 'title' => 'سياسة', 'file_size' => 3072]);

    $html = (string) $this->get('/governance')->assertOk()->getContent();

    expect(substr_count($html, 'class="file-card'))->toBe(2)
        ->and($html)->toContain(e(__('site.file.size_mb', ['size' => '2.0'])))
        ->toContain(e(__('site.file.size_kb', ['size' => 3])))
        ->toContain(route('governance.show', 'bylaws'))
        ->toContain(route('governance.download', 'bylaws'));
});
