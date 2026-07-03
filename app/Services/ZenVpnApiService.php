<?php

namespace App\Services;

use App\Exceptions\ZenVpnApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZenVpnApiService
{
    private string $baseUrl;
    private string $adminUsername;
    private string $adminPassword;

    /** Cache key for the bearer token */
    private const TOKEN_CACHE_KEY = 'fastapi_token';

    /** Token TTL in seconds — 50 minutes (tokens typically expire in 60 min) */
    private const TOKEN_TTL = 3000;

    public function __construct()
    {
        $this->baseUrl       = rtrim((string) config('services.zenvpn_api.url'), '/');
        $this->adminUsername = (string) config('services.zenvpn_api.username');
        $this->adminPassword = (string) config('services.zenvpn_api.password');
    }

    // ─── Authentication ───────────────────────────────────────────────────────

    /**
     * Retrieve a cached bearer token, or login to obtain a fresh one.
     *
     * @throws ZenVpnApiException
     */
    public function token(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_TTL, function () {
            return $this->login();
        });
    }

    /**
     * POST /auth/login — obtain a bearer token using admin credentials.
     *
     * @throws ZenVpnApiException
     */
    public function login(): string
    {
        try {
            $response = Http::withoutVerifying()
                ->asForm()
                ->timeout(10)
                ->post("{$this->baseUrl}/auth/login", [
                    'username'   => $this->adminUsername,
                    'password'   => $this->adminPassword,
                    'grant_type' => 'password',
                ]);

            if ($response->failed()) {
                Log::error('[ZenVPN API] Login failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new ZenVpnApiException('FastAPI authentication failed (HTTP ' . $response->status() . ').');
            }

            $token = $response->json('access_token');

            if (empty($token)) {
                throw new ZenVpnApiException('FastAPI returned an empty access token.');
            }

            return $token;
        } catch (ConnectionException $e) {
            Log::error('[ZenVPN API] Login connection error', ['error' => $e->getMessage()]);
            throw new ZenVpnApiException('Could not connect to the VPN backend: ' . $e->getMessage());
        }
    }

    // ─── Users ────────────────────────────────────────────────────────────────

    /**
     * POST /users — provision a new user on the backend.
     * Returns an array with vless_uuid, trojan_uuid, vmess_uuid.
     *
     * @throws ZenVpnApiException
     */
    public function createUser(string $username, string $plan = 'basic'): array
    {
        $postData = $this->request('post', '/users', [
            'username' => $username,
            'plan'     => $plan,
            'notes'    => 'Created via ZenVPN portal',
        ]);

        // Log the full POST response so we can see exactly what the backend returns
        Log::debug('[ZenVPN API] POST /users raw response', [
            'username' => $username,
            'response' => $postData,
        ]);

        // Extract UUIDs — try the POST response first
        $uuids = $this->extractUuids($postData);

        if (empty($uuids)) {
            Log::info('[ZenVPN API] UUIDs not in POST response, fetching via GET', ['username' => $username]);

            $userData = $this->getUser($username);

            // Log the full GET response as well
            Log::debug('[ZenVPN API] GET /users/{username} raw response', [
                'username' => $username,
                'response' => $userData,
            ]);

            $uuids = $this->extractUuids($userData);
        }

        if (empty($uuids)) {
            // Log the raw data at ERROR level so it is always visible, even outside debug mode
            Log::error('[ZenVPN API] UUID extraction failed — unrecognised response format', [
                'username'       => $username,
                'post_response'  => $postData,
                'get_response'   => $userData ?? [],
            ]);
            throw new ZenVpnApiException(
                "Could not retrieve UUIDs for user '{$username}' from the backend. " .
                "Check laravel.log for the raw API response to identify the correct field names."
            );
        }

        return $uuids;
    }

    /**
     * GET /users/{username} — fetch a user record from the backend.
     *
     * @throws ZenVpnApiException
     */
    public function getUser(string $username): array
    {
        return $this->request('get', "/users/{$username}");
    }

    /**
     * DELETE /users/{username} — remove a user from the backend.
     * Returns true on success; false if the user was not found (404).
     *
     * @throws ZenVpnApiException
     */
    public function deleteUser(string $username): bool
    {
        try {
            $response = Http::withoutVerifying()
                ->withToken($this->token())
                ->timeout(10)
                ->delete("{$this->baseUrl}/users/{$username}");

            if ($response->status() === 404) {
                Log::warning('[ZenVPN API] deleteUser: user not found', ['username' => $username]);
                return false;
            }

            if ($response->failed()) {
                throw new ZenVpnApiException("Delete user '{$username}' failed (HTTP {$response->status()}).");
            }

            return true;
        } catch (ConnectionException $e) {
            Log::error('[ZenVPN API] deleteUser connection error', ['error' => $e->getMessage()]);
            throw new ZenVpnApiException('Could not connect to the VPN backend: ' . $e->getMessage());
        }
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    /**
     * Execute an authenticated HTTP request; re-authenticate once on 401.
     *
     * @throws ZenVpnApiException
     */
    private function request(string $method, string $path, array $body = []): array
    {
        try {
            $response = $this->makeRequest($method, $path, $body, $this->token());

            // If unauthorized, clear cached token and retry once
            if ($response->status() === 401) {
                Cache::forget(self::TOKEN_CACHE_KEY);
                $response = $this->makeRequest($method, $path, $body, $this->token());
            }

            if ($response->failed()) {
                Log::error('[ZenVPN API] Request failed', [
                    'method' => $method,
                    'path'   => $path,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new ZenVpnApiException("API {$method} {$path} failed (HTTP {$response->status()}).");
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            Log::error('[ZenVPN API] Connection error', ['path' => $path, 'error' => $e->getMessage()]);
            throw new ZenVpnApiException('Could not connect to the VPN backend: ' . $e->getMessage());
        }
    }

    private function makeRequest(string $method, string $path, array $body, string $token)
    {
        $http = Http::withoutVerifying()
            ->withToken($token)
            ->timeout(10);

        return match (strtolower($method)) {
            'post'   => $http->post("{$this->baseUrl}{$path}", $body),
            'get'    => $http->get("{$this->baseUrl}{$path}"),
            'delete' => $http->delete("{$this->baseUrl}{$path}"),
            default  => throw new ZenVpnApiException("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * Attempt to extract vless_uuid, trojan_uuid, vmess_uuid from a response array.
     *
     * Format priority (most → least specific):
     *  1. devices[0] array:      { "devices": [{ "vless_uuid":"...", "trojan_uuid":"...", "vmess_uuid":"..." }] }
     *  2. Flat protocol fields:  { "vless_uuid":"...", "trojan_uuid":"...", "vmess_uuid":"..." }
     *  3. Single shared UUID:    { "uuid":"..." }  (one UUID for all protocols)
     *  4. Associative proxies:   { "proxies": { "vless":{...}, "trojan":{...}, "vmess":{...} } }
     *  5. Array of proxy objects:{ "proxies": [{"type":"vless","uuid":"..."}, ...] }
     */
    private function extractUuids(array $data): array
    {
        // ── 1. devices[0] — the format this backend actually uses ─────────────
        //    GET /users/{username} → { "devices": [{ "vless_uuid":"...", ... }] }
        if (isset($data['devices']) && is_array($data['devices']) && !empty($data['devices'])) {
            $dev    = $data['devices'][0];
            $vless  = $dev['vless_uuid']  ?? null;
            $trojan = $dev['trojan_uuid'] ?? null;
            $vmess  = $dev['vmess_uuid']  ?? null;

            if ($vless && $trojan && $vmess) {
                return ['vless_uuid' => $vless, 'trojan_uuid' => $trojan, 'vmess_uuid' => $vmess];
            }
        }

        // ── 2. Flat protocol-specific fields ─────────────────────────────────
        $vless  = $data['vless_uuid']  ?? null;
        $trojan = $data['trojan_uuid'] ?? $data['trojan_password'] ?? null;
        $vmess  = $data['vmess_uuid']  ?? null;

        if ($vless && $trojan && $vmess) {
            return ['vless_uuid' => $vless, 'trojan_uuid' => $trojan, 'vmess_uuid' => $vmess];
        }

        // ── 3. Single shared UUID (one UUID for VLESS/VMess; Trojan may use password) ──
        $sharedUuid = $data['uuid'] ?? $data['id'] ?? null;
        if ($sharedUuid) {
            return [
                'vless_uuid'  => $sharedUuid,
                'trojan_uuid' => $data['password'] ?? $sharedUuid,
                'vmess_uuid'  => $sharedUuid,
            ];
        }

        // ── 4. Associative proxies object ─────────────────────────────────────
        if (isset($data['proxies']) && is_array($data['proxies'])) {
            $proxies = $data['proxies'];

            if (isset($proxies['vless']) || isset($proxies['trojan']) || isset($proxies['vmess'])) {
                $vless  = $proxies['vless']['uuid']      ?? $proxies['vless']['id']       ?? null;
                $trojan = $proxies['trojan']['password'] ?? $proxies['trojan']['uuid']    ?? $proxies['trojan']['id'] ?? null;
                $vmess  = $proxies['vmess']['uuid']      ?? $proxies['vmess']['id']       ?? null;

                if ($vless && $trojan && $vmess) {
                    return ['vless_uuid' => $vless, 'trojan_uuid' => $trojan, 'vmess_uuid' => $vmess];
                }
            }

            // ── 5. Array of proxy objects ─────────────────────────────────────
            if (isset($proxies[0]) && is_array($proxies[0])) {
                $indexed = [];
                foreach ($proxies as $proxy) {
                    $type = strtolower($proxy['type'] ?? '');
                    if ($type) {
                        $indexed[$type] = $proxy;
                    }
                }

                $vless  = $indexed['vless']['uuid']      ?? $indexed['vless']['id']       ?? null;
                $trojan = $indexed['trojan']['password'] ?? $indexed['trojan']['uuid']    ?? $indexed['trojan']['id'] ?? null;
                $vmess  = $indexed['vmess']['uuid']      ?? $indexed['vmess']['id']       ?? null;

                if ($vless && $trojan && $vmess) {
                    return ['vless_uuid' => $vless, 'trojan_uuid' => $trojan, 'vmess_uuid' => $vmess];
                }
            }
        }

        return [];
    }
}
