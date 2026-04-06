<?php

namespace Database\Factories;

use App\Models\MirrorLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class MirrorLogFactory extends Factory
{
    protected $model = MirrorLog::class;

    public function definition(): array
    {
        $started = now()->subSeconds(rand(10, 120));

        return [
            'status'           => MirrorLog::STATUS_SUCCESS,
            'triggered_by'     => null,
            'started_at'       => $started,
            'completed_at'     => $started->copy()->addSeconds(rand(5, 60)),
            'duration_seconds' => rand(5, 60),
            'remote_host'      => 'db.example.com',
            'error_message'    => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn() => [
            'status'        => MirrorLog::STATUS_FAILED,
            'completed_at'  => now(),
            'error_message' => 'Connection refused',
        ]);
    }
}
