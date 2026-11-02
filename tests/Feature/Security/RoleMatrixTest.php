<?php

use App\Enums\RoleName;
use App\Filament\Resources\SitePages\SitePageResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

use function Livewire\invade;

/*
| مصفوفة الصلاحيات الكاملة (قرار صفوان، 20 أكتوبر 2026 — البند ٥ من مهمة تشديد الدخول): كل حالة (زائر وكل دور من الأدوار)
| على كل مسار GET في الموقع واللوحة وHorizon، بسجل حقيقي لكل شاشة في اللوحة من مصنعه، ومعها الإنشاء والتعديل والحذف من
| السياسات على السجل نفسه.
|
| والمتوقع لا يُشتق من الشيفرة نفسها، بل من ملف محفوظ راجعه إنسان (role-matrix.json، وخلاصته في تقرير تشديد الدخول): أي
| تغيير في وصول أي دور إلى أي مسار يُسقط الاختبار حتى يُراجع ويُحدَّث الملف عمداً:
|   UPDATE_ROLE_MATRIX=1 php artisan test tests/Feature/Security/RoleMatrixTest.php
| ولا 500 في أي خانة.
*/

const ROLE_MATRIX_FILE = __DIR__.'/role-matrix.json';

/**
 * @return array<string, array{0: ?RoleName}>
 */
function roleMatrixCases(): array
{
    $cases = ['guest' => [null]];

    foreach (RoleName::cases() as $role) {
        $cases[$role->value] = [$role];
    }

    return $cases;
}

/**
 * سجل حقيقي لكل مورد في اللوحة من مصنعه، فتبلغ شاشات العرض والتعديل فحص الصلاحية لا 404. وما لا مصنع له أو تعذّر
 * إنشاؤه يبقى بمعرّف لا وجود له.
 *
 * @return array<class-string, Model>
 */
function roleMatrixRecords(): array
{
    $records = [];

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        $model = $resource::getModel();

        if (isset($records[$model]) || ! method_exists($model, 'factory')) {
            continue;
        }

        try {
            $records[$model] = $model::factory()->create();
        } catch (Throwable) {
            // يبقى بمعرّف لا وجود له
        }
    }

    return $records;
}

/**
 * @param  array<class-string, Model>  $records
 */
function roleMatrixUri(Route $route, array $records): string
{
    $page = $route->getControllerClass();
    $record = null;

    if ($page !== null && is_subclass_of($page, ResourcePage::class)) {
        $record = $records[$page::getResource()::getModel()] ?? null;
    }

    return '/'.ltrim((string) preg_replace_callback('/\{([^}?:]+)(:[^}?]+)?\??\}/', function (array $m) use ($record): string {
        if ($m[1] === 'record' && $record !== null) {
            return (string) $record->getRouteKey();
        }

        // نسخة صفحة لا وجود لها بأي حال: فتح «محرر الصفحات» لمديري النظام في الاختبار نفسه ينشئ نسخاً، وتسلسل المعرّفات لا يعود
        // مع المعاملة، فرقم 1 موجود أحياناً وغائب أحياناً (المعاينة نفسها في PageEditorTest)
        if ($m[1] === 'revision') {
            return '0';
        }

        return str_contains($m[1].($m[2] ?? ''), 'uuid') || in_array($m[1], ['serviceRequest', 'registration', 'entry', 'payment', 'round', 'submission'], true) ? (string) Str::uuid() : '1';
    }, $route->uri()), '/');
}

/**
 * الرد بصيغة ثابتة: الرمز، والتحويل بمساره بلا معرّفات.
 */
function roleMatrixOutcome(int $status, ?string $location): string
{
    if ($status < 300 || $status >= 400) {
        return (string) $status;
    }

    $path = (string) parse_url((string) $location, PHP_URL_PATH);

    return 'redirect:'.preg_replace(['/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '#/\d+(?=/|$)#'], ['{uuid}', '/{id}'], $path === '' ? '/' : $path);
}

it('gives every role exactly the reviewed access to every GET route and panel record', function (?RoleName $role) {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $records = roleMatrixRecords();
    $user = $role === null ? null : userWithRole($role);

    $routes = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => in_array('GET', $route->methods(), true)
            && ! str_starts_with($route->uri(), 'livewire')
            && ! str_starts_with($route->uri(), '_'))
        ->sortBy(fn (Route $route) => $route->uri());

    $actual = ['routes' => [], 'policies' => []];
    $errors = [];

    foreach ($routes as $route) {
        app('auth')->forgetGuards();
        $request = $user === null ? $this : $this->actingAs($user);
        $response = $request->get(roleMatrixUri($route, $records));

        if ($response->status() >= 500) {
            $errors[] = $route->uri();
        }

        $actual['routes'][$route->uri()] = roleMatrixOutcome($response->status(), $response->headers->get('Location'));
    }

    foreach ($records as $model => $record) {
        $gate = Gate::forUser($user);
        $actual['policies'][class_basename($model)] = ($gate->allows('create', $model) ? 'c' : '-')
            .($gate->allows('update', $record) ? 'u' : '-')
            .($gate->allows('delete', $record) ? 'd' : '-');
    }
    ksort($actual['policies']);

    expect($errors)->toBe([]);

    $key = $role->value ?? 'guest';
    $saved = is_file(ROLE_MATRIX_FILE) ? json_decode((string) file_get_contents(ROLE_MATRIX_FILE), true) : [];

    if (getenv('UPDATE_ROLE_MATRIX') === '1') {
        $saved[$key] = $actual;
        ksort($saved);
        file_put_contents(ROLE_MATRIX_FILE, json_encode($saved, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }

    expect($saved)->toHaveKey($key);

    // الفرق وحده في رسالة الفشل، لا المصفوفة كلها
    $diff = [];
    foreach (['routes', 'policies'] as $part) {
        foreach ($actual[$part] + $saved[$key][$part] as $name => $ignored) {
            if (($actual[$part][$name] ?? null) !== ($saved[$key][$part][$name] ?? null)) {
                $diff[$part.' '.$name] = ['reviewed' => $saved[$key][$part][$name] ?? null, 'now' => $actual[$part][$name] ?? null];
            }
        }
    }

    expect($diff)->toBe([]);
})->with(roleMatrixCases());

// المستفيد لا يصل إلى أي شاشة في اللوحة، والمشرف العام يصل إلى كل قائمة فيها: حدّان لا يحتاجان إلى المراجعة
it('keeps the two ends of the matrix: a member opens no panel screen, the super admin opens every list', function () {
    $saved = json_decode((string) file_get_contents(ROLE_MATRIX_FILE), true);

    foreach ($saved['member']['routes'] as $uri => $outcome) {
        if (str_starts_with($uri, 'admin') || str_starts_with($uri, 'horizon')) {
            expect($outcome)->not->toBe('200', $uri);
        }
    }

    // والمستثنى بمفتاحه: المدفوعات المؤرشفة (PAYMENTS_ENABLED=false)، وتحليل الإحصائيات حتى يُضبط مزوده
    // و«محرر الصفحات» لمديري النظام وحدهما لا للمشرف العام (قرار صفوان، 31 أكتوبر 2026 — docs/decisions/2026-11-02-page-editor.md)
    $switchedOff = ['admin/payments', 'admin/stats-analysis', 'admin/site-pages'];

    foreach ($saved['super_admin']['routes'] as $uri => $outcome) {
        if (str_starts_with($uri, 'admin/') && ! str_contains($uri, '{') && ! str_ends_with($uri, '/create') && ! in_array($uri, $switchedOff, true)) {
            expect($outcome)->toBeIn(['200', 'redirect:/admin'], $uri);
        }
    }

    expect(array_keys($saved))->toEqualCanonicalizing(array_keys(roleMatrixCases()));
});

// الجميع غير المستفيد بلا تحقق ثنائي لا يدخل اللوحة: الأدوار التي تشترطه تُحوَّل إلى إعداده
it('sends a role that needs two-factor to its setup before any panel screen', function (RoleName $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $response = $this->actingAs($user)->get('/admin');

    expect($response->status())->toBeIn([302, 403])
        ->and($response->status() === 302 ? (string) $response->headers->get('Location') : '')->not->toContain('/admin/');
})->with(fn () => array_map(fn (RoleName $role) => [$role], RoleName::twoFactorRoles()));

// Filament يسجّل مسار صفوف الاستيراد الفاشلة ولا جدول imports في البوابة: كان 500 لأي زائر (AppServiceProvider)
it('answers 404 on the unused Filament import route instead of a database error', function () {
    $this->get('/filament/imports/1/failed-rows/download')->assertNotFound();
    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get('/filament/imports/1/failed-rows/download')->assertNotFound();
});

it('limits the import 404 to the Filament route, leaving any other {import} parameter to its usual binding', function () {
    Router::middleware('web')->get('/test-import-param/{import}', fn (string $import) => 'got '.$import)->name('test.import-param');

    $this->get('/test-import-param/abc')->assertOk()->assertSee('got abc');
});

// محاولات الكتابة في اللوحة لكل دور بـ Livewire (مراجعة المشرف على #64): شاشة الإنشاء وشاشة التعديل لكل مورد، على سجل
// حقيقي من مصنعه. والمتوقع من خانات السياسات في الملف المراجَع (c وu)، لا من سلوك الشيفرة: ما لا يحق للدور لا تُفتح
// شاشته (403 أو 404)، فلا يصل إلى «حفظ» ولا «حذف»، والسجل كما هو. وما يحق له يُفتح، شاهداً على أن الرفض من الصلاحية.
it('refuses or allows each panel create and edit screen to each role exactly as the reviewed policies say', function (RoleName $role) {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $records = roleMatrixRecords();
    $saved = json_decode((string) file_get_contents(ROLE_MATRIX_FILE), true)[$role->value]['policies'];
    $this->actingAs(userWithRole($role));
    $wrong = [];
    $checked = 0;

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        $model = $resource::getModel();
        $record = $records[$model] ?? null;
        $policy = $saved[class_basename($model)] ?? null;

        // و«محرر الصفحات» وحده يحصر سجلاته في صفحات الموقع الثابتة، فلا يدخلها سجل المصنع ولا تُقاس شاشته بخانات النموذج العامة
        // (صلاحيته pages.edit_all — PageEditorTest). والتخطي باسم المورد لا باستعلامه، لأن الاستعلام يُقرأ بدور المختبَر فيتخطى غيره
        if ($record === null || $policy === null || $resource === SitePageResource::class) {
            continue;
        }

        $before = $record->fresh()?->getAttributes();

        foreach (['create' => 'c', 'edit' => 'u'] as $name => $letter) {
            $page = ($resource::getPages()[$name] ?? null)?->getPage();

            if ($page === null) {
                continue;
            }

            $allowed = str_contains($policy, $letter);
            $checked++;

            try {
                $status = invade(Livewire::test($page, $name === 'edit' ? ['record' => $record->getRouteKey()] : []))->lastState->getResponse()->status();
            } catch (Throwable $exception) {
                $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            }

            if ($allowed ? $status !== 200 : ! in_array($status, [403, 404], true)) {
                $wrong[class_basename($page)] = ['policy' => $policy, 'status' => $status];
            }
        }

        if ($record->fresh()?->getAttributes() != $before) {
            $wrong[class_basename($model).' changed'] = true;
        }
    }

    // كل مورد له سجل وشاشة، لا مرور فارغ
    expect($checked)->toBeGreaterThan(40)
        ->and($wrong)->toBe([]);
})->with(fn () => array_map(fn (RoleName $role) => [$role], RoleName::cases()));

// محاولات كتابة فعلية لكل دور (بقايا مراجعة #64، ومهمة لأن المشرف العام صار اطلاعاً فقط — 25 أكتوبر 2026): «حفظ» في شاشة
// التعديل، و«حذف» في الجدول وفي شاشة التعديل، والحذف الجماعي، على سجل حقيقي من مصنعه، لما لا يحق للدور في الملف المراجَع.
// وكل محاولة يُتأكد من رفضها بعينه (مراجعة المشرف، 26 أكتوبر 2026): الشاشة لا تُفتح (403 أو 404 أو رفض صلاحية أو مصادقة)،
// أو تُفتح والإجراء فيها مخفي أو معطّل ثم يُستدعى فلا يُنفَّذ. وأي نتيجة غيرها خطأ باسمها. والسجل كما هو بعد كل المحاولات.
it('refuses every real save, delete, table and bulk attempt by a role without the right, and leaves the record unchanged', function (RoleName $role) {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $records = roleMatrixRecords();
    $saved = json_decode((string) file_get_contents(ROLE_MATRIX_FILE), true)[$role->value]['policies'];
    $this->actingAs(userWithRole($role));
    $wrong = [];
    $attempted = 0;

    // null متى رُفضت المحاولة، وإلا وصف ما حدث
    $refused = function (Closure $mount, ?Closure $hidden, Closure $act): ?string {
        try {
            $page = $mount();
        } catch (AuthorizationException|AuthenticationException) {
            return null;
        } catch (HttpExceptionInterface $exception) {
            return in_array($exception->getStatusCode(), [403, 404], true) ? null : 'HTTP '.$exception->getStatusCode();
        }

        $status = invade($page)->lastState->getResponse()->status();

        if (in_array($status, [403, 404], true)) {
            return null;
        }

        if ($status !== 200 || $hidden === null) {
            return 'opened ('.$status.')';
        }

        if ($hidden($page->instance())) {
            return 'action visible and enabled';
        }

        try {
            $act($page);
        } catch (AuthorizationException|HttpExceptionInterface|AssertionFailedError|ActionNotResolvableException) {
            // الإجراء الغائب أو المخفي أو المعطّل لا يُنفَّذ، ورفضه بأي من هذه هو المتوقع
        }

        return null;
    };

    // الإجراء متاح للدور: موجود، وظاهر، وغير معطّل (وفي الجدول: السجل في قائمته)
    $available = fn (?Action $action): bool => $action !== null && $action->isVisible() && ! $action->isDisabled();

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        $model = $resource::getModel();
        $record = $records[$model] ?? null;
        $policy = $saved[class_basename($model)] ?? null;

        if ($record === null || $policy === null || (str_contains($policy, 'u') && str_contains($policy, 'd'))) {
            continue;
        }

        $before = $record->fresh()?->getAttributes();
        $pages = $resource::getPages();
        $list = ($pages['index'] ?? null)?->getPage();
        $edit = ($pages['edit'] ?? null)?->getPage();
        $attempts = [];

        if ($list !== null && ! str_contains($policy, 'd')) {
            $row = TestAction::make('delete')->table($record);
            $bulk = TestAction::make('delete')->table()->bulk();
            $attempts['table delete'] = [fn () => Livewire::test($list),
                fn ($page): bool => $page->getTableRecord((string) $record->getKey()) !== null && $available($page->getTable()->getAction('delete')?->record($record)),
                fn ($page) => $page->callAction($row)];
            $attempts['bulk delete'] = [fn () => Livewire::test($list),
                fn ($page): bool => $page->getTableRecord((string) $record->getKey()) !== null && $available($page->getTable()->getBulkAction('delete')),
                // وجدول بلا حذف جماعي لا يُستدعى فيه (يرجع Filament إلى إجراء الصف بلا سجل)
                fn ($page) => $page->instance()->getTable()->getBulkAction('delete') === null ? null : $page->selectTableRecords([$record->getKey()])->callAction($bulk)];
        }

        if ($edit !== null) {
            $mount = fn () => Livewire::test($edit, ['record' => $record->getRouteKey()]);

            if (! str_contains($policy, 'u')) {
                // بلا حق التعديل لا تُفتح شاشة التعديل أصلاً
                $attempts['save'] = [$mount, null, fn ($page) => $page->call('save')];
            }

            if (! str_contains($policy, 'd')) {
                $attempts['edit delete'] = [$mount, fn ($page): bool => $available($page->getAction('delete')), fn ($page) => $page->callAction('delete')];
            }
        }

        foreach ($attempts as $name => [$mount, $hidden, $act]) {
            $attempted++;

            if (($outcome = $refused($mount, $hidden, $act)) !== null) {
                $wrong[class_basename($model).' '.$name] = $outcome;
            }
        }

        $after = $record->fresh()?->getAttributes();

        if (! str_contains($policy, 'd') && $after === null) {
            $wrong[class_basename($model)] = 'deleted';
        } elseif (! str_contains($policy, 'u') && $after !== null && $after != $before) {
            $wrong[class_basename($model)] = 'updated';
        }
    }

    expect($attempted)->toBeGreaterThan(0)
        ->and($wrong)->toBe([]);
})->with(fn () => array_map(fn (RoleName $role) => [$role], array_values(array_filter(RoleName::cases(), fn (RoleName $role): bool => ! in_array($role, RoleName::administrators(), true)))));
