<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->must_change_password && ! $request->routeIs(
            'auth.me',
            'auth.logout',
            'auth.password.change',
        )) {
            return ApiResponse::error([
                'password' => ['You must change your password before continuing.'],
            ], 403);
        }

        return $next($request);
    }
}
