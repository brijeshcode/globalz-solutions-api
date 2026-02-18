<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiHits
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        Log::channel('api_hits')->info('API Hit', [
            'method' => $request->method(),
            'url' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration' => $duration . 'ms',
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        return $response;
    }
}
