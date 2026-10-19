<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * معرّف سجل لا يصلح لعمود مفتاحه في رابط اللوحة (/admin/inquiries/abc، أو create لمورد بلا صفحة إنشاء) 404 قبل
 * أي استعلام. وإلا رفضه PostgreSQL على العمود الرقمي (22P02) فصار 500 (إعادة التجربة الشاملة، 19 أكتوبر 2026).
 * والموارد التي ترابط بـ uuid تبقى كما هي: سجلها الغائب 404 من استعلامها.
 */
class RejectMalformedAdminRecord
{
    public function handle(Request $request, Closure $next): Response
    {
        $record = $request->route('record');
        $page = $request->route()?->getControllerClass();

        if (is_string($record) && is_string($page) && is_subclass_of($page, Page::class)) {
            $resource = $page::getResource();
            /** @var Model $model */
            $model = app($resource::getModel());
            $column = $resource::getRecordRouteKeyName() ?? $model->getRouteKeyName();

            // bigint حتى 18 رقماً، فلا يتجاوز الرقم الطويل حده (22003)
            abort_if(
                $column === $model->getKeyName() && $model->getKeyType() === 'int' && preg_match('/^[0-9]{1,18}$/', $record) !== 1,
                404,
            );
        }

        return $next($request);
    }
}
