<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحة «بياناتي». الحفظ نفسه عبر مسارات Fortify:
 * user-profile-information.update و user-password.update
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()]);
    }
}
