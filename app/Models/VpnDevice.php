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
        'sni',
        'vpn_username',
        'device_identifier',
    ];

    protected $casts = [
        'last_seen'  => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * SNI options available in the UI.
     * Key = domain, value = human-readable label.
     */
    public const SNI_OPTIONS = [
        'm.zoom.us'           => 'Zoom',
        'www.google.com'      => 'Google',
        'www.facebook.com'    => 'Facebook',
        'www.microsoft.com'   => 'Microsoft',
        'www.cloudflare.com'  => 'Cloudflare',
        'www.apple.com'       => 'Apple',
        'teams.microsoft.com' => 'Microsoft Teams',
        'web.whatsapp.com'    => 'WhatsApp Web',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── VPN URI Helpers ─────────────────────────────────────────────────────

    /** VLESS URI for this device using its stored SNI */
    public function getVlessUri(): string
    {
        $encoded = rawurlencode($this->device_name);
        $sni     = $this->sni ?? 'm.zoom.us';
        $host    = config('services.zenvpn.server_host', 'zenvpnsl.duckdns.org');

        return "vless://{$this->vless_uuid}@{$host}:443?type=tcp&security=tls&sni={$sni}#{$encoded}";
    }

    /** Trojan URI for this device using its stored SNI */
    public function getTrojanUri(): string
    {
        $encoded = rawurlencode($this->device_name);
        $sni     = $this->sni ?? 'm.zoom.us';
        $host    = config('services.zenvpn.server_host', 'zenvpnsl.duckdns.org');

        return "trojan://{$this->trojan_uuid}@{$host}:443?sni={$sni}#{$encoded}";
    }

    /** VMess URI for this device (standard base64 JSON format) */
    public function getVmessUri(): string
    {
        $host   = config('services.zenvpn.server_host', 'zenvpnsl.duckdns.org');
        $config = [
            'v'    => '2',
            'ps'   => $this->device_name,
            'add'  => $host,
            'port' => '443',
            'id'   => $this->vmess_uuid,
            'aid'  => '0',
            'net'  => 'tcp',
            'type' => 'none',
            'tls'  => 'tls',
            'sni'  => $this->sni ?? 'm.zoom.us',
        ];

        return 'vmess://' . base64_encode(json_encode($config));
    }

    /** Human-readable SNI label (e.g. "Zoom") */
    public function sniLabel(): string
    {
        return self::SNI_OPTIONS[$this->sni] ?? $this->sni;
    }
}
