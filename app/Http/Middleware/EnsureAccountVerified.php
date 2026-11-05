<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يحل محل «verified» في لارافيل (bootstrap/app.php): يمر الحساب ما دام التوثيق مطفأً من اللوحة، وإلا لزمه بريد موثق أو
 * جوال موثق (User::isVerified). ومن لم يوثّق يُحوَّل إلى توثيق ما عنده: البريد، ثم الجوال، ثم «بياناتي» ليضيف أحدهما.
 */
class EnsureAccountVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isVerified()) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 403, __('account.verification.required'));

        $route = match (true) {
            $user->email !== null => 'verification.notice',
            $user->phoneToVerify() !== null => 'account.phone.verify',
            default => 'account.profile',
        };

        return redirect()->guest(route($route))->with('status', __('account.verification.required'));
    }
}
