<?php

namespace Database\Factories;

use App\Models\ClientHeartbeat;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientHeartbeatFactory extends Factory
{
    protected $model = ClientHeartbeat::class;

    public function definition(): array
    {
        return [
            'client_id' => \App\Models\Client::factory(),
            'ip_address' => $this->faker->ipv4(),
            'rtt_ms' => $this->faker->numberBetween(10, 200),
            'status_code' => $this->faker->randomElement([200, 200, 200, 500]),
            'created_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
        ];
    }
}