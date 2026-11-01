<?php

namespace App\Http\Controllers;

use App\Enums\OpeningKind;
use App\Models\Opening;
use App\Models\ServiceRequest;
use App\Support\MenuSections;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * صفحات الخدمات الإلكترونية العامة: بطاقات الخدمات، والفرص المنشورة بأنواعها، وصفحة كل فرصة.
 */
class ServicesController extends Controller
{
    public function index(): View
    {
        // بطاقة وحدة مبنية، وبشرطها إن كان لها (قياس الرضا بوجود استبيان عام مفتوح). والشرط نفسه في قائمة الرأس (MenuSections)
        return view('services.index', ['tiles' => MenuSections::availableServices()]);
    }

    public function membership(): View
    {
        return $this->listing(OpeningKind::Membership);
    }

    public function jobs(): View
    {
        return $this->listing(OpeningKind::Employment);
    }

    public function volunteering(): View
    {
        return $this->listing(OpeningKind::Volunteering);
    }

    public function show(Request $request, string $slug): Response
    {
        $opening = Opening::query()->published()->where('slug', $slug)->firstOrFail();

        $existing = $request->user() === null ? null : ServiceRequest::query()
            ->where('opening_id', $opening->id)
            ->where('user_id', $request->user()->id)
            ->first();

        $response = response()->view('services.show', ['opening' => $opening, 'existing' => $existing]);

        // الصفحة لصاحب الحساب تحمل رقم طلبه المرجعي، فلا تُحفظ في ذاكرة متصفح ولا وسيط
        return $request->user() === null ? $response : $response->header('Cache-Control', 'private, no-store');
    }

    private function listing(OpeningKind $kind): View
    {
        $openings = Opening::query()
            ->published()
            ->where('kind', $kind->value)
            ->where(fn ($query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->orderByRaw('closes_at asc nulls last')
            ->orderByDesc('id')
            ->paginate(12);

        abort_if($openings->currentPage() > max($openings->lastPage(), 1), 404);

        return view('services.listing', ['kind' => $kind, 'openings' => $openings]);
    }
}
