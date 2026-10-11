<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Registers\LogRegisterAccess;
use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Models\RegisterEntry;
use App\Models\User;
use App\Support\PrivatePdf;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تنزيل مرفق مستند من السجلات: الرابط مؤقت موقّع (المسار)، والصلاحية تُفحص هنا من جديد عند الطلب،
 * ويُسجَّل التنزيل بالمنفّذ والملف. والمستند المحذوف لا يُطلب (ربط المسار يستثنيه).
 */
class RegisterAttachmentController extends Controller
{
    public function __invoke(Request $request, RegisterEntry $entry, int $index, LogRegisterAccess $log): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canAccessPanel(Filament::getPanel('admin')) && $user->can('view', $entry), 403);

        $file = ($entry->attachments ?? [])[$index] ?? abort(404);
        abort_unless(PrivatePdf::inDirectory($file['path'], RegisterEntry::DIRECTORY), 404);
        $log->handle($user, $entry, ActivityEvent::FileDownloaded, ['file' => $file['name']]);

        return Storage::disk(RegisterEntry::disk())->download($file['path'], $file['name']);
    }
}
