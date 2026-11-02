<?php

namespace App\Http\Controllers;

use App\Models\PageRevision;
use App\Support\EditablePages;
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

        return response()->view('content.page-preview', [
            'revision' => $revision,
            'content' => $content,
            'key' => EditablePages::keyForSlug($revision->page->slug),
            'image' => $content['image'] !== null ? Storage::disk('public')->url($content['image']) : null,
        ])->withHeaders([
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
