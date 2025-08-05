<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        // Rate limiting for login attempts
        $key = 'login.' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return ApiResponse::throw(
                [],
                'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
                429
            );
        }

        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:6'
            ]);
        } catch (ValidationException $e) {
            RateLimiter::hit($key);
            return ApiResponse::failValidation($e->errors(), 'Login validation failed');
        }

        // Find user (excluding soft deleted users automatically)
        $user = User::where('email', $validated['email'])->first();

        // Verify credentials
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($key);
            return ApiResponse::throw(
                ['email' => ['The provided credentials are incorrect.']],
                'Invalid credentials',
                401
            );
        }

        // Check if user account is active
        if (!$user->is_active) {
            RateLimiter::hit($key);
            return ApiResponse::throw(
                ['email' => ['Your account has been deactivated. Please contact administrator.']],
                'Account deactivated',
                401
            );
        }

        // Clear rate limiter on successful login
        RateLimiter::clear($key);

        // Generate token for web app
        $token = $user->createToken('web-app-token');

        // Update last login timestamp using the model method
        $user->updateLastLogin();

        return ApiResponse::send('Login successful', 200, [
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
        ]);
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
}
