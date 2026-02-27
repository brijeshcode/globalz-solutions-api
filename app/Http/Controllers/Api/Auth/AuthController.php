<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\Advance\LoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * User Login with Rate Limiting
     * 
     * Authenticate user with email and password to receive an access token.
     * Includes rate limiting (5 attempts per IP) and comprehensive security checks.
     * 
     * @unauthenticated
     * 
     * @bodyParam email string required User's email address. Example: user@example.com
     * @bodyParam password string required User's password. Example: password123
     * 
     * @response 200 {
     *   "message": "Login successful",
     *   "data": {
     *     "user": {
     *       "id": 1,
     *       "name": "John Doe",
     *       "email": "user@example.com",
     *       "role": "admin",
     *       "email_verified_at": "2024-01-01T10:00:00.000000Z",
     *       "created_at": "2024-01-01T10:00:00.000000Z"
     *     },
     *     "token": "1|abc123def456ghi789...",
     *     "token_type": "Bearer"
     *   }
     * }
     * 
     * @response 401 {
     *   "message": "Invalid credentials",
     *   "errors": {
     *     "email": ["The provided credentials are incorrect."]
     *   }
     * }
     * 
     * @response 429 {
     *   "message": "Too many login attempts. Please try again in 60 seconds."
     * }
     */
    public function login(LoginRequest  $request): JsonResponse
    {
        // Rate limiting configuration
        $maxAttempts = config('auth.login_rate_limit.max_attempts', 2);
        $decaySeconds = config('auth.login_rate_limit.decay_seconds', 3600);
        $key = 'login.' . $request->ip();

        // Check rate limit
        if ($rateLimitResponse = $this->checkRateLimit($key, $maxAttempts)) {
            return $rateLimitResponse;
        }

        // Validate request
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:6'
            ]);
        } catch (ValidationException $e) {
            RateLimiter::hit($key, $decaySeconds);

            $attempts = RateLimiter::attempts($key);
            $remainingAttempts = $maxAttempts - $attempts;

            $errorMessage = 'Login validation failed';
            if ($remainingAttempts > 0) {
                $errorMessage .= '. Warning: You have ' . $remainingAttempts . ' attempt(s) remaining before your IP is blocked for 1 hour.';
            }

            return ApiResponse::failValidation($e->errors(), $errorMessage);
        }

        // Authenticate user
        $result = $this->authenticateUser($validated, $request, $key, $decaySeconds, $maxAttempts);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        // Clear rate limiter on successful authentication
        RateLimiter::clear($key);

        // Process successful login
        $data = $this->processSuccessfulLogin($result, $request);

        return ApiResponse::send('Login successful', 200, $data);
    }

    /**
     * Logout user and revoke current token
     * 
     * Revoke the current access token to log out the user from current device.
     * 
     * @authenticated
     * 
     * @response 200 {
     *   "message": "Logout successful"
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        // Get the current token name to extract login log ID
        $tokenName = $request->user()->currentAccessToken()->name;

        // Extract login log ID from token name (format: web-app-token-{id})
        if (preg_match('/web-app-token-(\d+)/', $tokenName, $matches)) {
            $loginLogId = $matches[1];
            $loginLog = LoginLog::find($loginLogId);

            if ($loginLog) {
                $loginLog->markLogout();
            }
        }

        // Revoke current access token
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::send('Logout successful', 200);
    }

    /**
     * Logout from all devices (revoke all tokens)
     * 
     * Revoke all tokens for the authenticated user to log out from all devices.
     * 
     * @authenticated
     * 
     * @response 200 {
     *   "message": "Logged out from all devices successfully"
     * }
     */
    public function logoutAll(Request $request): JsonResponse
    {
        // Mark all active login sessions as logged out
        LoginLog::where('user_id', $request->user()->id)
            ->whereNull('logout_at')
            ->update(['logout_at' => now()]);

        // Revoke all tokens for this user
        $request->user()->tokens()->delete();

        return ApiResponse::send('Logged out from all devices successfully', 200);
    }

    /**
     * Get authenticated user information
     *
     * Retrieve the current authenticated user's information.
     *
     * @authenticated
     *
     * @response 200 {
     *   "message": "User information retrieved successfully",
     *   "data": {
     *     "user": {
     *       "id": 1,
     *       "name": "John Doe",
     *       "email": "user@example.com",
     *       "role": "admin",
     *       "email_verified_at": "2024-01-01T10:00:00.000000Z",
     *       "last_login_at": "2024-01-01T12:00:00.000000Z",
     *       "created_at": "2024-01-01T10:00:00.000000Z"
     *     }
     *   }
     * }
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::send('User information retrieved successfully', 200, [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
            ]
        ]);
    }

    /**
     * Auto logout all users (scheduled task)
     *
     * Automatically logout all users by revoking all tokens and marking all active login sessions as logged out.
     * This is typically called by a scheduled task.
     *
     * @return array
     */
    public static function autoLogoutAllUsers(): array
    {
        $loginLogsUpdated = 0;
        $tokensDeleted = 0;

        // Get all users with their tokens using eager loading to avoid N+1 queries
        $users = User::has('tokens')->with('tokens')->get();

        foreach ($users as $user) {
            foreach ($user->tokens as $token) {
                // Extract login log ID from token name (format: web-app-token-{id})
                if (preg_match('/web-app-token-(\d+)/', $token->name, $matches)) {
                    $loginLogId = $matches[1];
                    $loginLog = LoginLog::find($loginLogId);

                    if ($loginLog && is_null($loginLog->logout_at)) {
                        $loginLog->markLogout();
                        $loginLogsUpdated++;
                    }
                }
            }

            // Delete all tokens for this user
            $tokensDeleted += $user->tokens()->delete();
        }

        return [
            'users_processed' => $users->count(),
            'login_logs_updated' => $loginLogsUpdated,
            'tokens_deleted' => $tokensDeleted,
            'logged_out_at' => now()->toDateTimeString()
        ];
    }

    /**
     * Unlock a blocked IP address
     *
     * Manually clear the rate limiter for a specific IP address to unblock it.
     *
     * @authenticated
     *
     * @bodyParam ip_address string required The IP address to unlock. Example: 192.168.1.100
     *
     * @response 200 {
     *   "message": "IP address 192.168.1.100 has been unlocked successfully"
     * }
     */
    public function unlockIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip_address' => 'required|ip'
        ]);

        $key = 'login.' . $validated['ip_address'];
        RateLimiter::clear($key);

        return ApiResponse::send('IP address ' . $validated['ip_address'] . ' has been unlocked successfully', 200);
    }

    /**
     * Check if rate limit is exceeded
     */
    private function checkRateLimit(string $key, int $maxAttempts): ?JsonResponse
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = ceil($seconds / 60);

            return ApiResponse::throw(
                [],
                'Too many login attempts. Your IP has been blocked. Please try again in ' . $minutes . ' minute(s).',
                429
            );
        }

        return null;
    }

    /**
     * Handle rate limit hit and build error response with remaining attempts warning
     */
    private function handleRateLimitedError(string $key, int $decaySeconds, int $maxAttempts, array $errors, string $baseMessage): JsonResponse
    {
        RateLimiter::hit($key, $decaySeconds);

        $attempts = RateLimiter::attempts($key);
        $remainingAttempts = $maxAttempts - $attempts;

        $errorMessage = $baseMessage;
        if ($remainingAttempts > 0) {
            $errorMessage .= ' <br> Warning: You have ' . $remainingAttempts . ' attempt(s) remaining before your IP is blocked for 1 hour.';
        }

        return ApiResponse::throw($errors, $errorMessage, 401);
    }

    /**
     * Authenticate user credentials
     */
    private function authenticateUser(array $validated, Request $request, string $key, int $decaySeconds, int $maxAttempts): User|JsonResponse
    {
        // Find user
        $user = User::where('email', $validated['email'])->first();

        // Check if user exists
        if (!$user) {
            // Log failed login attempt with email and password
            LoginLog::logFailedLogin(
                null,
                null,
                $request->ip(),
                $request->userAgent() ?? 'Unknown',
                $validated['email'],
                $validated['password'],
                'User not found - Invalid email address'
            );

            return $this->handleRateLimitedError(
                $key,
                $decaySeconds,
                $maxAttempts,
                ['email' => ['The provided credentials are incorrect.']],
                ''
            );
        }

        // Verify password
        if (!Hash::check($validated['password'], $user->password)) {
            // Log failed login attempt with email and password
            LoginLog::logFailedLogin(
                $user->id,
                $user->role,
                $request->ip(),
                $request->userAgent() ?? 'Unknown',
                $validated['email'],
                $validated['password'],
                'Invalid password - Password does not match'
            );

            return $this->handleRateLimitedError(
                $key,
                $decaySeconds,
                $maxAttempts,
                ['email' => ['The provided credentials are incorrect.']],
                ''
            );
        }

        // Check if user account is active
        if (!$user->is_active) {
            // Log failed login attempt due to inactive account with email
            LoginLog::logFailedLogin(
                $user->id,
                $user->role,
                $request->ip(),
                $request->userAgent() ?? 'Unknown',
                $validated['email'],
                null, // Don't store password for inactive accounts as credentials are correct
                'Account deactivated - User account is not active'
            );

            return $this->handleRateLimitedError(
                $key,
                $decaySeconds,
                $maxAttempts,
                ['email' => ['Your account has been deactivated. Please contact administrator.']],
                'Account deactivated'
            );
        }

        return $user;
    }

    /**
     * Process successful login and generate token
     */
    private function processSuccessfulLogin(User $user, Request $request): array
    {
        // Single login enforcement: revoke all existing sessions if enabled
        if (config('app.single_login_per_user', false)) {
            LoginLog::where('user_id', $user->id)
                ->whereNull('logout_at')
                ->update(['logout_at' => now()]);

            $user->tokens()->delete();
        }

        // Generate token for web app
        $token = $user->createToken('web-app-token');

        // Update last login timestamp
        $user->updateLastLogin();

        // Log successful login
        $loginLog = LoginLog::logSuccessfulLogin(
            $user->id,
            $user->role,
            $request->ip(),
            $request->userAgent() ?? 'Unknown',
            $user->email
        );

        // Store login log ID in token metadata for logout tracking
        $token->accessToken->forceFill([
            'name' => 'web-app-token-' . $loginLog->id
        ])->save();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ],
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer'
        ];
    }
}
