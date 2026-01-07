<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'uuid' => (string) Str::uuid(),
            'public_ip' => $this->faker->ipv4(),
            'is_online' => $this->faker->boolean(),
            'last_heartbeat' => $this->faker->boolean() ? $this->faker->dateTimeBetween('-1 hour', 'now') : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
