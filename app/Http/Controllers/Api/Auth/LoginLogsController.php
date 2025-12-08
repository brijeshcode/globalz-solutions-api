<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Resources\LoginLogResource;
use App\Models\Advance\LoginLog;
use App\Traits\HasPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class LoginLogsController extends Controller
{
    use HasPagination;

    /**
     * Get paginated login logs with optional filters
     *
     * @authenticated
     *
     * @queryParam user_id integer Filter by user ID. Example: 1
     * @queryParam login_successful boolean Filter by login status (true/false). Example: true
     * @queryParam ip_address string Filter by IP address. Example: 192.168.1.1
     * @queryParam date_from string Filter logs from this date (Y-m-d). Example: 2024-01-01
     * @queryParam date_to string Filter logs until this date (Y-m-d). Example: 2024-12-31
     * @queryParam per_page integer Number of items per page. Example: 20
     */
    public function index(Request $request)
    {
        $query = LoginLog::with('user:id,name,email')
            ->orderBy('id', 'desc');

        // Filter by user ID
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by login status
        if ($request->filled('login_successful')) {
            $query->where('login_successful', filter_var($request->login_successful, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by IP address
        if ($request->filled('ip_address')) {
            $query->where('ip_address', $request->ip_address);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('login_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('login_at', '<=', $request->to_date);
        }

        $log = $this->applyPagination($query, $request);

        // Get blocked IPs
        $blockedIps = $this->getBlockedIps();

        // Get paginated response
        $response = ApiResponse::paginated(
            'Login logs retrieved successfully',
            $log,
            LoginLogResource::class
        );

        // Add blocked IPs to response data
        $responseData = $response->getData(true);
        $responseData['data']['blocked_ips'] = $blockedIps;
        $responseData['data']['total_blocked_ips'] = count($blockedIps);

        return response()->json($responseData, $response->status());
    }

    /**
     * Get all currently blocked IP addresses
     *
     * @return array
     */
    private function getBlockedIps(): array
    {
        $maxAttempts = config('auth.login_rate_limit.max_attempts', 2);
        $blockedIps = [];

        // Query cache table for login rate limit keys
        $cacheKeys = DB::table('cache')
            ->where('key', 'like', '%login.%')
            ->get();

        foreach ($cacheKeys as $cacheEntry) {
            // Extract IP from cache key (format: laravel_cache:login.192.168.1.1)
            if (preg_match('/login\.(.+)$/', $cacheEntry->key, $matches)) {
                $ipAddress = $matches[1];
                $key = 'login.' . $ipAddress;

                // Check if this IP is currently blocked
                if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                    $remainingSeconds = RateLimiter::availableIn($key);
                    $remainingMinutes = ceil($remainingSeconds / 60);
                    $blockedUntil = now()->addSeconds($remainingSeconds);

                    $blockedIps[] = [
                        'ip_address' => $ipAddress,
                        'remaining_seconds' => $remainingSeconds,
                        'remaining_minutes' => $remainingMinutes,
                        'blocked_until' => $blockedUntil->format('Y-m-d H:i:s')
                    ];
                }
            }
        }

        return $blockedIps;
    }
}
