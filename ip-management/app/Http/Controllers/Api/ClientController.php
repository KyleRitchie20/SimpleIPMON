<?php

namespace App\Http\Controllers\Api;

use App\Models\Client;
use App\Models\ClientHeartbeat;
use App\Models\ClientMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientController
{
    public function install(Request $request)
    {
        $validated = $request->validate(['name' => 'required|string|max:255|unique:clients,name']);

        $uuid = (string) Str::uuid();
        $client = Client::create([
            'name' => $validated['name'],
            'uuid' => $uuid,
            'is_online' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Client registered successfully',
            'uuid' => $uuid,
            'heartbeat_interval' => 300,
            'endpoint' => route('api.heartbeat'),
        ], 201);
    }

    public function heartbeat(Request $request)
    {
        $token = $request->bearerToken() ?? $request->query('token');
        if (!$token) {
            return response()->json(['error' => 'Missing authentication token'], 401);
        }

        $client = Client::where('uuid', $token)->firstOrFail();

        $validated = $request->validate([
            'ip_address' => 'required|ip',
            'rtt_ms' => 'required|integer|min:0|max:60000',
        ]);

        ClientHeartbeat::createFromHeartbeat($client->id, [
            'ip_address' => $validated['ip_address'],
            'rtt_ms' => $validated['rtt_ms'],
        ]);

        $client->update([
            'public_ip' => $validated['ip_address'],
            'is_online' => true,
            'last_heartbeat' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Heartbeat received',
            'timestamp' => now()->toIso8601String(),
            'next_heartbeat_in' => 300,
        ]);
    }

    public function listClients()
    {
        $clients = Client::with(['heartbeats' => fn($q) => $q->recent()->limit(10)])
            ->orderBy('last_heartbeat', 'desc')
            ->get();

        $data = $clients->map(fn($client) => [
            'id' => $client->id,
            'name' => $client->name,
            'status' => $client->status,
            'public_ip' => $client->public_ip,
            'is_online' => $client->is_online,
            'last_heartbeat' => $client->last_heartbeat?->toIso8601String(),
            'minutes_since_heartbeat' => $client->last_heartbeat ? now()->diffInMinutes($client->last_heartbeat) : null,
            'avg_rtt_1h' => $client->average_rtt_1h,
            'avg_rtt_24h' => $client->average_rtt_24h,
            'uptime_24h' => round($client->uptime_percentage, 2),
        ]);

        return response()->json(['success' => true, 'count' => $clients->count(), 'data' => $data]);
    }

    public function getMetrics($clientId, Request $request)
    {
        $client = Client::findOrFail($clientId);
        $hoursBack = $request->query('hours', 24);
        $fromTime = now()->subHours($hoursBack);

        $metrics = ClientMetric::where('client_id', $clientId)
            ->where('metric_type', 'rtt_avg')
            ->where('recorded_at', '>=', $fromTime)
            ->orderBy('recorded_at', 'asc')
            ->get();

        $chartData = $metrics->map(fn($m) => [
            'timestamp' => $m->recorded_at->toIso8601String(),
            'avg_rtt' => $m->avg_rtt_ms,
            'min_rtt' => $m->min_rtt_ms,
            'max_rtt' => $m->max_rtt_ms,
        ]);

        return response()->json([
            'success' => true,
            'client_name' => $client->name,
            'period_hours' => $hoursBack,
            'metric_count' => $metrics->count(),
            'data' => $chartData,
            'stats' => [
                'avg_rtt' => round($metrics->avg('avg_rtt_ms'), 2),
                'min_rtt' => $metrics->min('min_rtt_ms'),
                'max_rtt' => $metrics->max('max_rtt_ms'),
            ],
        ]);
    }

    public function deleteClient($clientId)
    {
        $client = Client::findOrFail($clientId);
        $clientName = $client->name;
        $client->delete();

        return response()->json(['success' => true, 'message' => "Client '$clientName' deleted successfully"]);
    }
}