<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientMetric extends Model
{
    public $timestamps = false;
    protected $fillable = ['client_id', 'metric_type', 'metric_value', 'min_rtt_ms', 'max_rtt_ms', 'avg_rtt_ms', 'recorded_at'];
    protected $casts = ['recorded_at' => 'datetime', 'avg_rtt_ms' => 'float'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public static function aggregateFromHeartbeats($clientId, $fromTime)
    {
        $heartbeats = ClientHeartbeat::where('client_id', $clientId)
            ->where('created_at', '>=', $fromTime)
            ->where('status_code', 200)
            ->get();

        if ($heartbeats->isEmpty()) return null;

        return self::create([
            'client_id' => $clientId,
            'metric_type' => 'rtt_avg',
            'metric_value' => $heartbeats->avg('rtt_ms'),
            'min_rtt_ms' => $heartbeats->min('rtt_ms'),
            'max_rtt_ms' => $heartbeats->max('rtt_ms'),
            'avg_rtt_ms' => $heartbeats->avg('rtt_ms'),
            'recorded_at' => now(),
        ]);
    }
}