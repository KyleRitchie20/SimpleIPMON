<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Client Model
 *
 * Represents a monitored client in the IP Management System.
 * Tracks client status, connectivity, and performance metrics.
 */
class Client extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'uuid', 'public_ip', 'is_online', 'last_heartbeat'];
    protected $casts = ['is_online' => 'boolean', 'last_heartbeat' => 'datetime'];

    public function heartbeats(): HasMany
    {
        return $this->hasMany(ClientHeartbeat::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ClientMetric::class);
    }

    public function getStatusAttribute(): string
    {
        if (!$this->last_heartbeat) return 'PENDING';
        if ($this->last_heartbeat->diffInMinutes(now()) > 5) {
            // Update is_online status if it's still true but should be false
            if ($this->is_online) {
                $this->update(['is_online' => false]);
            }
            return 'OFFLINE';
        }

        // Update is_online status if it's false but should be true
        if (!$this->is_online) {
            $this->update(['is_online' => true]);
        }
        return 'ONLINE';
    }

    public function getAverageRtt1hAttribute(): ?float
    {
        return $this->heartbeats()
            ->where('created_at', '>=', now()->subHour())
            ->avg('rtt_ms');
    }

    public function getAverageRtt24hAttribute(): ?float
    {
        return $this->heartbeats()
            ->where('created_at', '>=', now()->subDay())
            ->avg('rtt_ms');
    }

    public function getUptimePercentageAttribute(): float
    {
        $heartbeats = $this->heartbeats()
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at')
            ->get();

        if ($heartbeats->isEmpty()) {
            return 0.0;
        }

        // Simple and reliable approach: count missed heartbeats
        $totalHeartbeats = $heartbeats->count();
        $missedHeartbeats = 0;

        $previousHeartbeat = $heartbeats->first()->created_at;

        foreach ($heartbeats as $index => $heartbeat) {
            // Skip the first heartbeat
            if ($index === 0) continue;

            $currentTime = $heartbeat->created_at;
            $gapMinutes = $previousHeartbeat->diffInMinutes($currentTime);

            // If gap > 5 minutes, count missed heartbeats
            if ($gapMinutes > 5) {
                $missedHeartbeats += floor(($gapMinutes - 5) / 5);
            }

            $previousHeartbeat = $currentTime;
        }

        // Calculate expected total heartbeats (actual + missed)
        $expectedHeartbeats = $totalHeartbeats + $missedHeartbeats;

        // Calculate uptime percentage
        if ($expectedHeartbeats <= 0) {
            return 100.0;
        }

        $uptimePercentage = ($totalHeartbeats / $expectedHeartbeats) * 100;

        return round(max(0, min(100, $uptimePercentage)), 1);
    }
}
