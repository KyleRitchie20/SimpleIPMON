<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function it_can_install_a_new_client()
    {
        $response = $this->postJson('/api/install', [
            'name' => 'Test Client'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'uuid',
                'heartbeat_interval',
                'endpoint',
                'installer_url'
            ]);

        $this->assertDatabaseHas('clients', [
            'name' => 'Test Client',
            'is_online' => false
        ]);
    }

    /** @test */
    public function it_validates_client_name_on_installation()
    {
        $response = $this->postJson('/api/install', [
            'name' => 'AB'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function it_rejects_duplicate_client_names()
    {
        Client::factory()->create(['name' => 'Existing Client']);

        $response = $this->postJson('/api/install', [
            'name' => 'Existing Client'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function it_requires_authentication_token_for_heartbeat()
    {
        $response = $this->postJson('/api/heartbeat', [
            'ip_address' => '192.168.1.1',
            'rtt_ms' => 100
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Missing authentication token']);
    }

    /** @test */
    public function it_validates_token_format_for_heartbeat()
    {
        $response = $this->postJson('/api/heartbeat', [
            'ip_address' => '192.168.1.1',
            'rtt_ms' => 100
        ], ['Authorization' => 'Bearer invalid-token']);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Invalid token format']);
    }

    /** @test */
    public function it_requires_valid_client_for_heartbeat()
    {
        $response = $this->postJson('/api/heartbeat', [
            'ip_address' => '192.168.1.1',
            'rtt_ms' => 100
        ], ['Authorization' => 'Bearer 12345678-1234-1234-1234-123456789012']);

        $response->assertStatus(404)
            ->assertJson(['error' => 'Client not found']);
    }

    /** @test */
    public function it_validates_heartbeat_data()
    {
        $client = Client::factory()->create();

        $response = $this->postJson('/api/heartbeat', [
            'ip_address' => 'invalid-ip',
            'rtt_ms' => 100
        ], ['Authorization' => 'Bearer ' . $client->uuid]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ip_address']);
    }

    /** @test */
    public function it_processes_valid_heartbeat()
    {
        $client = Client::factory()->create();

        $response = $this->postJson('/api/heartbeat', [
            'ip_address' => '192.168.1.1',
            'rtt_ms' => 100
        ], ['Authorization' => 'Bearer ' . $client->uuid]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Heartbeat received'
            ]);

        $this->assertDatabaseHas('client_heartbeats', [
            'client_id' => $client->id,
            'ip_address' => '192.168.1.1',
            'rtt_ms' => 100
        ]);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'public_ip' => '192.168.1.1',
            'is_online' => true
        ]);
    }

    /** @test */
    public function it_requires_authentication_for_client_list()
    {
        $response = $this->getJson('/api/clients');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_lists_clients_for_authenticated_users()
    {
        $user = \App\Models\User::factory()->create();
        $client = Client::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/clients');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'count',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'status',
                        'public_ip',
                        'is_online',
                        'last_heartbeat',
                        'minutes_since_heartbeat',
                        'avg_rtt_1h',
                        'avg_rtt_24h',
                        'uptime_24h'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_client_metrics()
    {
        $client = Client::factory()->create();

        $response = $this->getJson("/api/clients/{$client->id}/metrics");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_metrics_for_valid_client()
    {
        $user = \App\Models\User::factory()->create();
        $client = Client::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/clients/{$client->id}/metrics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'client_name',
                'period_hours',
                'metric_count',
                'data',
                'stats' => [
                    'avg_rtt',
                    'min_rtt',
                    'max_rtt'
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_deleting_client()
    {
        $client = Client::factory()->create();

        $response = $this->deleteJson("/api/clients/{$client->id}");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_deletes_client_for_authenticated_users()
    {
        $user = \App\Models\User::factory()->create();
        $client = Client::factory()->create();

        $response = $this->actingAs($user)->deleteJson("/api/clients/{$client->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => "Client '{$client->name}' deleted successfully"
            ]);

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }
}
