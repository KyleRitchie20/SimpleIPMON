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
        if (!$this->is_online) return 'OFFLINE';
        if (!$this->last_heartbeat) return 'PENDING';
        return now()->diffInMinutes($this->last_heartbeat) > 10 ? 'STALE' : 'ONLINE';
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
        $expected = 288; // 12 heartbeats/hour * 24 hours
        $actual = $this->heartbeats()
            ->where('created_at', '>=', now()->subDay())
            ->count();
        return ($actual / $expected) * 100;
    }
}
