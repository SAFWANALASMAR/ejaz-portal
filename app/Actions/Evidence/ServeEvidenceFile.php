<?php

namespace App\Actions\Evidence;

use App\Models\ArchiveItem;
use App\Models\EvidenceGalleryItem;
use App\Support\PrivateFileResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ملف من الأرشيف في معرض الشواهد: برابط موقّع قصير يصدره المعرض بعد فحص رمزه (EvidenceGalleryController::show)، ولا
 * يحمل الرمز نفسه. ويُفحص عند كل طلب: التوقيع ومدته، وأن الرابط ما زال سارياً من الإصدار نفسه (لا ملغى ولا منتهٍ
 * ولا مجدَّد ولا مُصدره معطّل)، وأن مُصدره ما زال يطّلع على العنصر، وأن الملف من المختار في البند. وكل رفض 404 واحدة.
 * والتقديم بنمط الملفات الخاصة: من التطبيق على القرص المحلي، وبرابط تخزين موقّع أقصر على S3 (PrivateFileResponse).
 * والصورة تُعرض، وملف PDF يُنزَّل.
 */
class ServeEvidenceFile
{
    public function handle(Request $request, EvidenceGalleryItem $item, string $key): Response
    {
        abort_unless($request->hasValidSignature(), 404);

        $item->loadMissing(['gallery.issuer', 'archiveItem.category']);
        $gallery = $item->gallery;
        abort_unless($gallery !== null && $gallery->linkIsActive() && hash_equals($gallery->linkKey(), (string) $request->query('link')), 404);
        abort_unless($item->visibleTo($gallery), 404);

        $file = collect($item->archiveFiles())->first(fn (array $file): bool => hash_equals(EvidenceGalleryItem::fileKey($file['path']), $key));
        abort_if($file === null, 404);

        $mime = EvidenceGalleryItem::imageMime($file['path']) ?? 'application/pdf';

        return PrivateFileResponse::make(ArchiveItem::disk(), $file['path'], $mime, $file['name'], (int) config('security.evidence_storage_url_minutes'), download: $mime === 'application/pdf');
    }
}
