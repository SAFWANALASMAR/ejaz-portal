<?php

namespace App\Http\Controllers\Assembly;

use App\Actions\Assembly\LogFileDownload;
use App\Enums\AssemblyFileAudience;
use App\Enums\AssemblyFileCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assembly\AssemblyFilesRequest;
use App\Models\AssemblyFile;
use App\Models\User;
use App\Support\AssemblyArea;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * مكتبة ملفات الأعضاء: القائمة بتصنيفاتها (وملفات المجلس لمجلس الإدارة وحده)، والتنزيل برابط موقّع بعد
 * فحص الصلاحية (AssemblyFilePolicy::download)، ويُسجَّل.
 */
class AssemblyFileController extends Controller
{
    private const PER_PAGE = 20;

    public function index(AssemblyFilesRequest $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $category = AssemblyFileCategory::tryFrom((string) $request->validated('category', ''));

        $files = AssemblyFile::query()
            ->unless(AssemblyArea::board($user), fn ($query) => $query->where('audience', AssemblyFileAudience::Members))
            ->when($category !== null, fn ($query) => $query->where('category', $category))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        abort_if($files->currentPage() > max(1, $files->lastPage()), 404);

        return view('assembly.files.index', ['files' => $files, 'active' => $category]);
    }

    public function download(Request $request, AssemblyFile $file, LogFileDownload $log): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('download', $file), 403);

        $log->handle($user, $file, 'file');

        return Storage::disk(AssemblyFile::disk())->download($file->file, $file->downloadName(), ['Content-Type' => 'application/pdf']);
    }
}
