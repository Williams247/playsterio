<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CookieTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->bearerToken() && $token = $request->cookie('auth_token')) {
            $request->headers->set('Authorization', "Bearer $token");
        }

        return $next($request);
    }
}

?>
