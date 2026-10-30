<?php

namespace App\Http\Controllers;

use App\Actions\Evidence\OpenEvidenceGallery;
use App\Actions\Evidence\ServeEvidenceFile;
use App\Models\EvidenceGallery;
use App\Models\EvidenceGalleryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * معرض الشواهد برابطه السري، بلا حساب (docs/decisions.md — 30 أكتوبر 2026). الصفحة لا تُفهرس ولا تُرسل الرابط في
 * Referer (SecurityHeaders)، ولا تُخزَّن. وصور الألبومات من القرص العام كما في مكتبة الصور، وملفات الأرشيف برابط
 * موقّع قصير بعد فحص الرمز، مما يطّلع عليه مُصدر الرابط وحده.
 */
class EvidenceGalleryController extends Controller
{
    public function show(Request $request, string $token, OpenEvidenceGallery $open): View
    {
        $gallery = $open->handle($token, $request->ip());
        abort_if($gallery === null, 404);

        $images = [];
        $files = [];
        $minutes = (int) config('security.evidence_file_url_minutes');

        foreach ($gallery->items as $item) {
            if (! $item->visibleTo($gallery)) {
                continue;
            }

            if ($item->album_id !== null) {
                foreach ($item->albumPhotos() as $path) {
                    $images[] = ['src' => Storage::disk('public')->url($path), 'alt' => (string) $item->album?->title];
                }

                continue;
            }

            foreach ($item->archiveFiles() as $file) {
                // صور وPDF وحدها (EvidenceGalleryItem::fileMime)، كما يقدّمها ServeEvidenceFile
                if (EvidenceGalleryItem::fileMime($file['path']) === null) {
                    continue;
                }

                $url = URL::temporarySignedRoute('evidence.file', now()->addMinutes($minutes), [
                    'item' => $item->id,
                    'key' => EvidenceGalleryItem::fileKey($file['path']),
                    'link' => $gallery->linkKey(),
                ]);

                if (EvidenceGalleryItem::imageMime($file['path']) !== null) {
                    $images[] = ['src' => $url, 'alt' => (string) $item->archiveItem?->title];
                } else {
                    $files[] = ['name' => $file['name'], 'url' => $url, 'size' => $file['size'], 'title' => (string) $item->archiveItem?->title];
                }
            }
        }

        return view('evidence.show', ['gallery' => $gallery, 'summary' => self::summary($gallery), 'images' => $images, 'files' => $files]);
    }

    public function file(Request $request, EvidenceGalleryItem $item, string $key, ServeEvidenceFile $serve): Response
    {
        return $serve->handle($request, $item, $key);
    }

    /**
     * سطر البرنامج والفترة تحت العنوان.
     */
    private static function summary(EvidenceGallery $gallery): ?string
    {
        $parts = array_filter([
            $gallery->program?->title,
            match (true) {
                $gallery->period_from !== null && $gallery->period_to !== null => __('evidence.page.period', ['from' => $gallery->period_from->format('Y-m-d'), 'to' => $gallery->period_to->format('Y-m-d')]),
                $gallery->period_from !== null => __('evidence.page.since', ['date' => $gallery->period_from->format('Y-m-d')]),
                $gallery->period_to !== null => __('evidence.page.until', ['date' => $gallery->period_to->format('Y-m-d')]),
                default => null,
            },
        ]);

        return $parts === [] ? null : implode(' — ', $parts);
    }
}
