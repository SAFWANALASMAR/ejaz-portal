<?php

namespace App\Actions\Content;

use App\Enums\RedirectType;
use App\Models\Redirect;
use App\Models\User;
use App\Rules\PlainText;
use App\Support\RedirectMap;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * حفظ تحويل من الشاشة أو من أمر الاستيراد: الصلاحية، ثم المصدر بصيغته الموحدة، ثم وجهة داخلية
 * لا تحويل مفتوحاً فيها، ولا حلقة ولا سلسلة. والتحويل يُسجَّل ويمسح الخريطة المخزنة بنفسه
 * (LogsActivity وFlushesContentCache).
 */
class SaveRedirect
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Redirect $redirect, array $data): Redirect
    {
        $allowed = $redirect->exists ? $actor->can('update', $redirect) : $actor->can('create', Redirect::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        foreach (['source', 'target'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $data[$field] = $field === 'source' ? RedirectMap::normalize($data[$field]) : trim($data[$field]);
            }
        }

        /** @var array{source: string, target: string, type: string, is_active?: bool} $validated */
        $validated = Validator::make($data, [
            'source' => ['present', 'string', 'max:500', new PlainText, Rule::unique(Redirect::class)->ignore($redirect)],
            'target' => ['required', 'string', 'max:500'],
            'type' => ['required', Rule::enum(RedirectType::class)],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'source.unique' => __('redirects.errors.duplicate'),
        ], [
            'source' => __('redirects.fields.source'),
            'target' => __('redirects.fields.target'),
        ])->after(function ($validator) use ($data, $redirect): void {
            if ($validator->errors()->isEmpty()) {
                foreach (self::problems((string) $data['source'], (string) $data['target'], $redirect) as $field => $error) {
                    $validator->errors()->add($field, __($error));
                }
            }
        })->validate();

        // الجديد مفعّل ما لم يُعطَّل صراحة
        $redirect->fill([...($redirect->exists ? [] : ['is_active' => true]), ...$validated]);

        DB::transaction(fn () => $redirect->save());

        return $redirect;
    }

    /**
     * مفاتيح رسائل الخطأ لكل حقل، وفارغة إن صلح التحويل.
     *
     * @return array<string, string>
     */
    private static function problems(string $source, string $target, Redirect $redirect): array
    {
        if ($source === '') {
            return ['source' => 'redirects.errors.home'];
        }

        if (self::servedBySite($source)) {
            return ['source' => 'redirects.errors.served'];
        }

        if (! RedirectMap::isSafeTarget($target)) {
            return ['target' => 'redirects.errors.target'];
        }

        $targetPath = RedirectMap::normalize($target);

        if ($targetPath === $source) {
            return ['target' => 'redirects.errors.loop'];
        }

        // لا سلسلة: الوجهة ليست مصدر تحويل آخر، والمصدر ليس وجهة تحويل آخر
        $others = Redirect::query()->when($redirect->exists, fn ($query) => $query->whereKeyNot($redirect->getKey()));

        if ((clone $others)->where('source', $targetPath)->exists()) {
            return ['target' => 'redirects.errors.chain_target'];
        }

        if ((clone $others)->pluck('target')->contains(fn (string $other): bool => RedirectMap::normalize($other) === $source)) {
            return ['source' => 'redirects.errors.chain_source'];
        }

        return [];
    }

    /**
     * مسار تخدمه البوابة لا يصل إلى المسار الاحتياطي، فلا يعمل تحويله أبداً.
     */
    private static function servedBySite(string $source): bool
    {
        $path = '/'.implode('/', array_map(rawurlencode(...), explode('/', $source)));

        try {
            $route = app(Router::class)->getRoutes()->match(Request::create($path));
        } catch (HttpException) {
            return false;
        }

        return ! $route->isFallback;
    }
}
