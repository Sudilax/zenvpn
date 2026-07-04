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
        'data_used_mb',
    ];

    protected $casts = [
        'last_seen'    => 'datetime',
        'created_at'   => 'datetime',
        'data_used_mb' => 'integer',
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

    return "vless://{$this->vless_uuid}@{$host}:4443?encryption=none&security=tls&sni={$sni}&type=ws&host={$sni}&path=%2Fzen&allowInsecure=1#{$encoded}";
}

    /** Trojan URI for this device using its stored SNI */
    public function getTrojanUri(): string
{
    $encoded = rawurlencode($this->device_name);
    $sni     = $this->sni ?? 'm.zoom.us';
    $host    = config('services.zenvpn.server_host', 'zenvpnsl.duckdns.org');

    return "trojan://{$this->trojan_uuid}@{$host}:8443?security=tls&sni={$sni}&type=ws&host={$sni}&path=%2Ftrojan&allowInsecure=1#{$encoded}";
}

    /** VMess URI for this device (standard base64 JSON format) */
    public function getVmessUri(): string
    {
        $host   = config('services.zenvpn.server_host', 'zenvpnsl.duckdns.org');
        $sni    = $this->sni ?? 'm.zoom.us';
        $config = [
            'v'    => '2',
            'ps'   => $this->device_name,
            'add'  => $host,
            'port' => '80',
            'id'   => $this->vmess_uuid,
            'aid'  => '0',
            'net'  => 'ws',
            'type' => 'none',
            'host' => $sni,
            'path' => '/vmess',
            'tls'  => 'tls',
            'sni'  => $sni,
        ];

        return 'vmess://' . base64_encode(json_encode($config));
    }

    /** Hysteria2 URI for this device using its VLESS UUID and stored SNI */
    public function getHysteria2Uri(): string
    {
        $encoded = rawurlencode($this->device_name);
        $sni     = $this->sni ?? 'm.zoom.us';
        $host    = config('services.zenvpn.server_host', 'zenvpnsl.duckdns.org');

        return "hysteria2://{$this->vless_uuid}@{$host}:5443?insecure=1&sni={$sni}#{$encoded}";
    }

    /** Human-readable SNI label (e.g. "Zoom") */
    public function sniLabel(): string
    {
        return self::SNI_OPTIONS[$this->sni] ?? $this->sni;
    }

    /** Data used as GB, rounded to 2 decimal places */
    public function dataUsedGb(): float
    {
        return round($this->data_used_mb / 1024, 2);
    }

    /** Human-readable usage (MB or GB) */
    public function formattedUsage(): string
    {
        if ($this->data_used_mb >= 1024) {
            return round($this->data_used_mb / 1024, 2) . ' GB';
        }
        return $this->data_used_mb . ' MB';
    }


}
