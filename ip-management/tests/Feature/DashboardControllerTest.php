<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function it_can_view_client_details_with_heartbeats()
    {
        // Create a client
        $client = Client::factory()->create([
            'name' => 'Test Client',
            'is_online' => true,
            'last_heartbeat' => now(),
        ]);

        // Create some heartbeats with different timestamps to test the date calculation
        $heartbeats = [];
        $startTime = now()->subHours(4);

        for ($i = 0; $i < 10; $i++) {
            $heartbeats[] = ClientHeartbeat::factory()->create([
                'client_id' => $client->id,
                'ip_address' => '192.168.1.' . ($i + 1),
                'rtt_ms' => 50 + $i * 10,
                'created_at' => $startTime->addMinutes($i * 30),
            ]);
        }

        // Act as an authenticated user
        $user = \App\Models\User::factory()->create();
        $response = $this->actingAs($user)->get("/clients/{$client->id}");

        // Assert the response is successful
        $response->assertStatus(200);

        // Assert the view has the expected data
        $response->assertViewHas('client');
        $response->assertViewHas('chartData');

        // Get the view data
        $viewData = $response->original->getData();

        // Verify the chart data structure
        $this->assertArrayHasKey('rtt_labels', $viewData['chartData']);
        $this->assertArrayHasKey('rtt_data', $viewData['chartData']);
        $this->assertArrayHasKey('uptime_labels', $viewData['chartData']);
        $this->assertArrayHasKey('uptime_data', $viewData['chartData']);
        $this->assertArrayHasKey('overall_uptime', $viewData['chartData']);

        // Verify the uptime calculation doesn't throw an error
        $this->assertIsNumeric($viewData['chartData']['overall_uptime']);
        $this->assertGreaterThanOrEqual(0, $viewData['chartData']['overall_uptime']);
        $this->assertLessThanOrEqual(100, $viewData['chartData']['overall_uptime']);
    }

    /** @test */
    public function it_handles_client_with_no_heartbeats()
    {
        // Create a client with no heartbeats
        $client = Client::factory()->create([
            'name' => 'Client No Heartbeats',
            'is_online' => false,
            'last_heartbeat' => null,
        ]);

        // Act as an authenticated user
        $user = \App\Models\User::factory()->create();
        $response = $this->actingAs($user)->get("/clients/{$client->id}");

        // Assert the response is successful
        $response->assertStatus(200);

        // Get the view data
        $viewData = $response->original->getData();

        // Verify empty chart data for no heartbeats
        $this->assertEmpty($viewData['chartData']['rtt_labels']);
        $this->assertEmpty($viewData['chartData']['rtt_data']);
        $this->assertEmpty($viewData['chartData']['uptime_labels']);
        $this->assertEmpty($viewData['chartData']['uptime_data']);
        $this->assertEquals(0, $viewData['chartData']['overall_uptime']);
    }

    /** @test */
    public function it_requires_authentication_for_client_view()
    {
        $client = Client::factory()->create();

        $response = $this->get("/clients/{$client->id}");

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /** @test */
    public function it_returns_404_for_nonexistent_client()
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get('/clients/99999');

        $response->assertStatus(404);
    }
}
