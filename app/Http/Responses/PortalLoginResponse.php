<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * مدخل كل بوابة بعد الدخول (requirements/12 §12): من يدخل لوحة الإدارة (الموظفون والمجلس والمحكّمون)
 * إلى /admin، والمشترك إلى لوحته (/account). والرابط المقصود قبل الدخول مقدَّم على الاثنين.
 */
class PortalLoginResponse implements LoginResponse, TwoFactorLoginResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $user = $request->user();
        $home = $user instanceof User && $user->canAccessPanel(Filament::getPanel('admin'))
            ? url(Filament::getPanel('admin')->getPath())
            : Fortify::redirects('login');

        return redirect()->intended($home);
    }
}
