<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCompetitionCertificate;
use App\Models\CompetitionSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * شهادة مسابقة الطلاب لصاحبها تنزيلاً. وتسجيل غيره أو بلا شهادة كأنه غير موجود.
 */
class CompetitionCertificateController extends Controller
{
    public function __invoke(Request $request, string $submission): StreamedResponse
    {
        /** @var CompetitionSubmission|null $record */
        $record = CompetitionSubmission::query()->where('uuid', $submission)->where('user_id', $request->user()?->id)->first();

        abort_if($record === null || blank($record->certificate_path), 404);

        return Storage::disk(GenerateCompetitionCertificate::disk())
            ->download((string) $record->certificate_path, 'certificate-'.$record->reference.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
