<?php

namespace App\Models\Advance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    /** @use HasFactory<\Database\Factories\Advance\LoginLogFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'user_role',
        'ip_address',
        'user_agent',
        'login_at',
        'logout_at',
        'login_successful',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'login_successful' => 'boolean',
    ];

    /**
     * Get the user that owns the login log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a successful login attempt.
     */
    public static function logSuccessfulLogin(int $userId, string $userRole, string $ipAddress, string $userAgent): self
    {
        return self::create([
            'user_id' => $userId,
            'user_role' => $userRole,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'login_at' => now(),
            'login_successful' => true,
        ]);
    }

    /**
     * Log a failed login attempt.
     */
    public static function logFailedLogin(?int $userId, ?string $userRole, string $ipAddress, string $userAgent): self
    {
        return self::create([
            'user_id' => $userId,
            'user_role' => $userRole,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'login_at' => now(),
            'login_successful' => false,
        ]);
    }

    /**
     * Mark logout time for this login session.
     */
    public function markLogout(): bool
    {
        return $this->update([
            'logout_at' => now(),
        ]);
    }
}
