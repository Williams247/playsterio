<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Merges raw JSON into the request when Content-Type is not application/json (api group only). */
class MergeJsonBody
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isJson() && $request->getContent() !== '') {
            $decoded = json_decode($request->getContent(), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge($decoded);
            }
        }

        return $next($request);
    }
}

?>
