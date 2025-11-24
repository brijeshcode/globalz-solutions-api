<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
            'user_role' => $this->user_role,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'login_at' => $this->login_at?->format('Y-m-d H:i:s'),
            'logout_at' => $this->logout_at?->format('Y-m-d H:i:s'),
            'login_successful' => $this->login_successful,
            'session_duration' => $this->getSessionDuration(),
        ];
    }

    /**
     * Calculate session duration in human-readable format
     */
    private function getSessionDuration(): ?string
    {
        if (!$this->login_at || !$this->logout_at) {
            return null;
        }

        $duration = $this->login_at->diff($this->logout_at);

        $parts = [];

        if ($duration->d > 0) {
            $parts[] = $duration->d . ' day' . ($duration->d > 1 ? 's' : '');
        }

        if ($duration->h > 0) {
            $parts[] = $duration->h . ' hour' . ($duration->h > 1 ? 's' : '');
        }

        if ($duration->i > 0) {
            $parts[] = $duration->i . ' minute' . ($duration->i > 1 ? 's' : '');
        }

        if (empty($parts)) {
            $parts[] = $duration->s . ' second' . ($duration->s > 1 ? 's' : '');
        }

        return implode(', ', $parts);
    }
}
