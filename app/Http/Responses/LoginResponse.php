<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();
        $base = config('app.base_domain');

        if ($user->hasRole('saas_admin')) {
            $url = $request->getScheme() . '://admin.' . $base . '/panel';
        } else {
            $url = $request->getScheme() . '://app.' . $base . '/dashboard';
        }

        return redirect($url);
    }
}
