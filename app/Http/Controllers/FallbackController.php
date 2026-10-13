<?php

namespace App\Http\Controllers;

use App\Support\LegacyPaths;
use App\Support\RedirectMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * المسار الاحتياطي: طلب GET لا يطابق أي مسار في البوابة. يُحوَّل إن كان رابطاً قديماً مسجلاً، أو
 * رابط ووردبريس قديماً إلى أقرب قسم (LegacyPaths)، وإلا فصفحة 404 العربية. ويمر بمجموعة web، فتأخذ صفحة 404 رؤوس الحماية، ورأساً بحالة الدخول.
 */
class FallbackController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $redirect = RedirectMap::find($request->path());

        if ($redirect !== null) {
            return redirect($redirect[0], $redirect[1]);
        }

        $nearest = LegacyPaths::nearest($request->path());

        abort_if($nearest === null, 404);

        return redirect($nearest, 301);
    }
}
