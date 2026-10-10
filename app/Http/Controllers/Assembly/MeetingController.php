<?php

namespace App\Http\Controllers\Assembly;

use App\Actions\Assembly\LogFileDownload;
use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * الاجتماعات في منطقة الأعضاء: القائمة (القادمة ثم السابقة)، وصفحة الاجتماع، والمحاضر المعتمدة، وتنزيل
 * المرفقات والمحضر المعتمد. والوصول في المسار (can:accessAssemblyArea)، ويُفحص هنا لكل سجل
 * (MeetingPolicy)، ويُسجَّل كل تنزيل.
 */
class MeetingController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request): View
    {
        $upcoming = Meeting::query()->generalAssembly()->where('status', MeetingStatus::Scheduled)->where('starts_at', '>=', now())->orderBy('starts_at')->get();
        $past = Meeting::query()->generalAssembly()->whereKeyNot($upcoming->modelKeys())->orderByDesc('starts_at')->orderByDesc('id')->paginate(self::PER_PAGE);

        abort_if($past->currentPage() > max(1, $past->lastPage()), 404);

        return view('assembly.meetings.index', ['upcoming' => $upcoming, 'past' => $past]);
    }

    public function show(Request $request, Meeting $meeting): View
    {
        abort_unless($this->user($request)->can('attend', $meeting), 403);

        return view('assembly.meetings.show', ['meeting' => $meeting, 'attendees' => $meeting->attendees()->count()]);
    }

    public function minutes(Request $request): View
    {
        $meetings = Meeting::query()->generalAssembly()->whereNotNull('minutes_file')->whereNotNull('minutes_approved_at')->orderByDesc('starts_at')->paginate(self::PER_PAGE);

        abort_if($meetings->currentPage() > max(1, $meetings->lastPage()), 404);

        return view('assembly.minutes.index', ['meetings' => $meetings]);
    }

    public function attachment(Request $request, Meeting $meeting, int $index, LogFileDownload $log): StreamedResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('attend', $meeting), 403);

        $file = ($meeting->attachments ?? [])[$index] ?? abort(404);
        $log->handle($user, $meeting, 'attachment:'.$index);

        return Storage::disk(Meeting::disk())->download($file['path'], $file['name'], ['Content-Type' => 'application/pdf']);
    }

    public function minutesFile(Request $request, Meeting $meeting, LogFileDownload $log): StreamedResponse
    {
        $user = $this->user($request);
        abort_unless($user->can('readMinutes', $meeting), 403);

        $log->handle($user, $meeting, 'minutes');

        return Storage::disk(Meeting::disk())->download((string) $meeting->minutes_file, 'minutes-'.$meeting->starts_at->format('Y-m-d').'.pdf', ['Content-Type' => 'application/pdf']);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
