<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VpnDevice extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'device_name',
        'vless_uuid',
        'trojan_uuid',
        'vmess_uuid',
        'status',
        'last_ip',
        'last_seen',
    ];

    protected $casts = [
        'last_seen'  => 'datetime',
        'created_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── VPN URI Helpers ─────────────────────────────────────────────────────

    /** VLESS URI for this device */
    public function getVlessUri(): string
    {
        $encoded = rawurlencode($this->device_name);
        return "vless://{$this->vless_uuid}@zenvpnsl.duckdns.org:443?type=tcp&security=tls&sni=zenvpnsl.duckdns.org#{$encoded}";
    }

    /** Trojan URI for this device */
    public function getTrojanUri(): string
    {
        $encoded = rawurlencode($this->device_name);
        return "trojan://{$this->trojan_uuid}@zenvpnsl.duckdns.org:443?sni=zenvpnsl.duckdns.org#{$encoded}";
    }

    /** VMess URI for this device (standard base64 JSON format) */
    public function getVmessUri(): string
    {
        $config = [
            'v'    => '2',
            'ps'   => $this->device_name,
            'add'  => 'zenvpnsl.duckdns.org',
            'port' => '443',
            'id'   => $this->vmess_uuid,
            'aid'  => '0',
            'net'  => 'tcp',
            'type' => 'none',
            'tls'  => 'tls',
        ];

        return 'vmess://' . base64_encode(json_encode($config));
    }
}
