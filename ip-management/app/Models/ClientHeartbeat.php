<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientHeartbeat extends Model
{
    public $timestamps = false;
    protected $fillable = ['client_id', 'ip_address', 'rtt_ms', 'status_code'];
    protected $casts = ['created_at' => 'datetime'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public static function createFromHeartbeat($clientId, array $data)
    {
        return self::create([
            'client_id' => $clientId,
            'ip_address' => $data['ip_address'],
            'rtt_ms' => (int) $data['rtt_ms'],
            'status_code' => $data['status_code'] ?? 200,
            'created_at' => now(),
        ]);
    }
}