<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Jenssegers\Agent\Agent;

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
            'user_agent_details' => $this->getUserAgentDetails(),
            'email' => $this->email,
            'password' => $this->password,
            'note' => $this->note,
            'login_at' => $this->login_at?->format('Y-m-d H:i:s'),
            'logout_at' => $this->logout_at?->format('Y-m-d H:i:s'),
            'login_successful' => $this->login_successful,
            'session_duration' => $this->getSessionDuration(),
        ];
    }

    /**
     * Parse user agent string to extract meaningful information
     */
    private function getUserAgentDetails(): ?array
    {
        if (!$this->user_agent) {
            return null;
        }

        $agent = new Agent();
        $agent->setUserAgent($this->user_agent);

        return [
            'browser' => $agent->browser() ?: 'Unknown',
            'browser_version' => $agent->version($agent->browser()) ?: 'Unknown',
            'platform' => $agent->platform() ?: 'Unknown',
            'platform_version' => $agent->version($agent->platform()) ?: 'Unknown',
            'device_type' => $this->getDeviceType($agent),
            'device' => $agent->device() ?: 'Unknown',
            'is_mobile' => $agent->isMobile(),
            'is_tablet' => $agent->isTablet(),
            'is_desktop' => $agent->isDesktop(),
            'is_robot' => $agent->isRobot(),
            'robot_name' => $agent->isRobot() ? $agent->robot() : null,
        ];
    }

    /**
     * Determine device type from agent
     */
    private function getDeviceType(Agent $agent): string
    {
        if ($agent->isRobot()) {
            return 'Robot/Bot';
        }

        if ($agent->isTablet()) {
            return 'Tablet';
        }

        if ($agent->isMobile()) {
            return 'Mobile';
        }

        if ($agent->isDesktop()) {
            return 'Desktop';
        }

        return 'Unknown';
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
