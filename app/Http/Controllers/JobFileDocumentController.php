<?php

namespace App\Http\Controllers;

use App\Actions\Activity\LogAccess;
use App\Enums\ActivityEvent;
use App\Models\JobFileDocument;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تنزيل مستند من ملف وظيفة برابط مؤقت موقّع: يُفحص عند الطلب الدخول والتحقق الثنائي (المسار)
 * والصلاحية (JobFileDocumentPolicy::view)، ويُسجَّل.
 */
class JobFileDocumentController extends Controller
{
    public function download(Request $request, JobFileDocument $document, LogAccess $log): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_active && $user->canAccessPanel(Filament::getPanel('admin')) && $user->can('view', $document), 403);

        $log->handle($user, $document, 'job_files', ActivityEvent::FileDownloaded, ['file' => $document->original_name]);

        return Storage::disk(JobFileDocument::disk())->download($document->path, $document->original_name);
    }
}
