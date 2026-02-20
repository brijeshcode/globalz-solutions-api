<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
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

        $tenant = Tenant::current();
        $tenantKey = $tenant?->tenant_key ?? 'unknown';

        $channel = $this->getTenantLogChannel($tenantKey);

        Log::build($channel)->info('API Hit', [
            'method' => $request->method(),
            'url' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration' => $duration . 'ms',
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
            'ip' => $request->ip(),
            'tenant' => $tenantKey,
        ]);

        return $response;
    }

    private function getTenantLogChannel(string $tenantKey): array
    {
        return [
            'driver' => 'single',
            'path' => storage_path("logs/api-hits-{$tenantKey}.log"),
            'level' => 'info',
        ];
    }
}
