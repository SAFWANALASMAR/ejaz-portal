<?php

use App\Enums\RoleName;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
| إعادة التجربة الشاملة (19 أكتوبر 2026، الخلل الجديد 1): معرّف غير رقمي في رابط سجل اللوحة كان يعطي 500
| (SQLSTATE 22P02)، ومنه create لمورد بلا صفحة إنشاء. والمتوقع 404 كالمعرّف الرقمي غير الموجود.
*/

/**
 * مسارات اللوحة التي تأخذ معرّف سجل وحده.
 *
 * @return list<string>
 */
function adminRecordUris(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'filament.admin.')
            && in_array('GET', $route->methods(), true)
            && $route->parameterNames() === ['record'])
        ->map(fn (RoutingRoute $route): string => $route->uri())
        ->values()
        ->all();
}

it('answers 404, not a server error, for a non-numeric record in any admin link', function () {
    $uris = adminRecordUris();
    expect(count($uris))->toBeGreaterThan(20);

    $this->actingAs(userWithRole(RoleName::SuperAdmin));

    // create مكان المعرّف للمورد الذي لا صفحة إنشاء له، وإلا فهي صفحته
    $defined = collect(Route::getRoutes()->getRoutes())->map(fn (RoutingRoute $route): string => $route->uri())->all();

    $wrong = [];
    foreach ($uris as $uri) {
        foreach (in_array(str_replace('{record}', 'create', $uri), $defined, true) ? ['abc', '12345678901234567890'] : ['abc', 'create', '12345678901234567890'] as $record) {
            $status = $this->get('/'.str_replace('{record}', $record, $uri))->getStatusCode();
            if ($status !== 404) {
                $wrong[] = "$uri ($record): $status";
            }
        }
    }

    expect($wrong)->toBe([]);
});

it('keeps the 404 for a numeric record that does not exist, and the pages without a create page', function () {
    $this->actingAs(userWithRole(RoleName::SuperAdmin));

    $this->get('/admin/inquiries/999999')->assertNotFound();
    $this->get('/admin/inquiries/create')->assertNotFound();
    $this->get('/admin/activities/create')->assertNotFound();
});
