<?php

namespace App\Http\Controllers;

use App\Models\PageRevision;
use App\Support\EditablePages;
use App\Support\NeutralImage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * معاينة نسخة من صفحة ثابتة قبل نشرها («محرر الصفحات»): رأس الصفحة وأقسامها كما ستُعرض. الرابط موقّع قصير المدة
 * (PageRevision::previewUrl)، ولا يفتحه إلا من دخل وله pages.edit_all، ولا يُخزَّن ولا يُفهرس.
 */
class PagePreviewController extends Controller
{
    public function __invoke(Request $request, PageRevision $revision): Response
    {
        abort_unless($request->user()?->can('preview', $revision) ?? false, 403);

        $content = EditablePages::normalize(is_array($revision->content) ? $revision->content : []);
        $key = (string) EditablePages::keyForSlug($revision->page->slug);

        return response()->view('content.page-preview', [
            'revision' => $revision,
            'content' => $content,
            'key' => $key,
            // بلا صورة رأس في النسخة: الرسم البديل لمسار الصفحة نفسها لا لمسار المعاينة، كما سيظهر بعد النشر (مراجعة المشرف على #124، ٥)
            'image' => $content['image'] !== null
                ? Storage::disk('public')->url($content['image'])
                : NeutralImage::forPage(EditablePages::PAGES[$key][1] ?? null),
        ])->withHeaders([
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
