# ZenVPN — Complete Project State & Handoff Document

**Purpose:** This is the full current-state handoff for a self-hosted VPN business project. It replaces prior AI-assistant context. Read this fully before making any changes.

**Owner:** Sudilax (GitHub) / Zen Strider — university student, Sri Lanka, Information Systems.

---

## 1. Project Overview

ZenVPN is a self-hosted, multi-protocol VPN service running on Oracle Cloud Free Tier, built for:
1. Personal use + friends (gaming, streaming, bypassing site blocks)
2. Portfolio/GitHub demonstration of full-stack + DevOps skill
3. Eventually, a low-cost or free (ad-supported) VPN product for Sri Lankan users

The business model has shifted over the project's life: originally planned as a paid subscription (PayHere integration), now leaning toward **free with ad monetization**, with a reserved bandwidth pool for the owner and select people, and a shared/limited pool for general free users.

---

## 2. Architecture

```
┌─────────────────────────────────────────────────────────┐
│  Laravel 13 (customer-facing web app)                    │
│  - Registration/login/dashboard (Breeze)                 │
│  - Filament v4 admin panel (/admin)                       │
│  - Calls FastAPI for all VPN provisioning                 │
└───────────────────────┬───────────────────────────────────┘
                         │ HTTPS (port 8001, Nginx-proxied)
┌───────────────────────▼───────────────────────────────────┐
│  FastAPI (Python, main.py)                                │
│  - JWT admin auth                                          │
│  - User/device CRUD                                        │
│  - Generates VPN connection URIs                           │
│  - Writes /etc/sing-box/users.json                          │
│  - Triggers sing-box reload                                 │
└───────────────────────┬───────────────────────────────────┘
                         │
┌───────────────────────▼───────────────────────────────────┐
│  sing-box (VPN engine)                                     │
│  - VLESS, Trojan, VMess, Shadowsocks, Hysteria2             │
│  - TLS camouflage via SNI spoofing (m.zoom.us default)      │
└─────────────────────────────────────────────────────────────┘

Background daemons (systemd services on VPS):
- zenvpn-tracker  → device_tracker.py   (device/expiry tracking, 30s loop)
- zenvpn-traffic  → traffic_accumulator.py (bandwidth polling via Clash API, 15s loop)
- zenvpn-api      → FastAPI (main.py, uvicorn, port 8000 internal)
```

**Why Laravel + FastAPI (not one framework):** FastAPI/Python was built first for the VPN engine management (matches sing-box's Python-adjacent tooling ecosystem, fast to prototype). Laravel was added later specifically for its mature auth/admin scaffolding (Breeze, Filament) to avoid hand-rolling user accounts. FastAPI was kept rather than replaced because it already worked and Laravel calls it as an internal service — not a redundant layer.

---

## 3. Infrastructure

| Item | Value |
|---|---|
| VPS provider | Oracle Cloud Free Tier (ARM64, Ampere A1) |
| Main VPN server IP | `129.150.32.96` |
| Domain | `zenvpnsl.duckdns.org` (DuckDNS, free) |
| OS | Ubuntu 24.04 LTS ARM64 |
| Resources | 2 OCPU / 12GB RAM (reduced from 4 OCPU/24GB after Oracle's June 2026 free-tier policy change) |
| SSL | Let's Encrypt (certbot) for website/API ports; self-signed cert for VPN protocol ports (intentional, see §5) |
| Other Oracle instances | `SaveSync` (140.245.112.77, 1 OCPU/6GB, unrelated project) and `iamzen` (138.2.101.26, 1 OCPU/6GB, backup VPN, not actively used) |
| Second unused domain | `zenvnet.duckdns.org` — registered but never used, safe to ignore or repurpose |

### Port Map
| Port | Service | Notes |
|---|---|---|
| 443 | Nginx → Laravel website | Real Let's Encrypt cert |
| 8001 | Nginx → FastAPI (proxied to internal 8000) | Real Let's Encrypt cert; needs `proxy_http_version 1.1` + `proxy_set_header Connection ""` in Nginx config — without this, intermittent SSL EOF errors occur (fixed once, may recur if Nginx config is regenerated) |
| 8000 | FastAPI (internal only) | uvicorn, systemd service `zenvpn-api` |
| 4443 | sing-box VLESS+WS+TLS | Self-signed cert, SNI camouflage |
| 8443 | sing-box Trojan+WS+TLS | Self-signed cert |
| 8880 | sing-box VMess+WS | No TLS at transport (VMess uses its own) |
| 8388 | sing-box Shadowsocks | TCP+UDP |
| 5443 | sing-box Hysteria2 | UDP, self-signed cert |
| 9090 | sing-box Clash API | Localhost-only (127.0.0.1), used by `traffic_accumulator.py` |
| 22 | SSH | — |

**Note:** Ports were deliberately moved off their "standard" numbers (e.g., VLESS off 443) specifically so Nginx could claim 443 for the website without conflict.

---

## 4. File Locations — CRITICAL, READ CAREFULLY

There are **two copies** of the sing-box-related Python scripts. This caused real confusion/bugs during development — any new agent must understand this:

| Path | Status | Purpose |
|---|---|---|
| `/etc/sing-box/*` | **LIVE, RUNNING** | This is what systemd services actually execute. All real fixes must go here. |
| `/etc/zenvpn/singbox/*` | Git-tracked copy | For GitHub portfolio visibility only. Must be manually kept in sync after fixing the live version — **this has drifted before**. |
| `/etc/zenvpn/api/main.py` | **LIVE, RUNNING** | This IS the real file (not a copy) — FastAPI's systemd service points directly here. Also git-tracked in place. |
| `/etc/zenvpn/web/*` | Legacy, being retired | Old vanilla-JS admin panel (`admin.html`) and landing page (`index.html`). Superseded by Laravel + Filament. Do not add new features here — see §5. |

### Two GitHub Repos
| Repo | Contents |
|---|---|
| `github.com/Sudilax/zenvpn` | Laravel application (local dev at `C:\Users\nethm\OneDrive\Desktop\ZenVpn` on Windows) |
| `github.com/Sudilax/zenvpn-backend` | FastAPI + sing-box scripts + legacy web files (mirrors `/etc/zenvpn` on the VPS) |

Both have a standing rule file at `.agents/AGENTS.md` (in the backend repo) instructing AI agents to **never auto-commit** — always stop after verifying changes work, and let the human run `git add/commit/push` manually. **This rule should be preserved and equally applied to any new local agent setup.**

---

## 5. Key Architectural Decisions & Why

1. **SNI camouflage (`m.zoom.us` default, user-selectable)** — VPN traffic disguises its TLS SNI as legitimate services (Zoom, Google, Facebook, etc.) to reduce chances of DPI-based blocking. This is why VPN-protocol ports use a **self-signed certificate** (not Let's Encrypt) — the cert's CN doesn't need to be trusted by real browsers, only the VPN client (with `allowInsecure=1` set), and a self-signed cert avoids exposing the real domain via SSL certificate transparency logs for the VPN ports specifically.

2. **One FastAPI user per Laravel customer, multiple devices nested under it** (not 1 FastAPI user per device) — original design mistakenly created a new backend user per device; refactored (Phase 5.5) so `users.fastapi_username` (Laravel) maps to exactly one FastAPI account, which internally holds a `devices` array with per-device UUIDs. Endpoints: `POST /users/{username}/devices` and `DELETE /users/{username}/devices/{id}` were added for this.

3. **Domain over IP in generated connection URIs** — `SERVER_DOMAIN` constant (`zenvpnsl.duckdns.org`) used in `generate_uris()` (FastAPI) and `config('services.zenvpn.server_host')` (Laravel), instead of the raw IP, so the VPN can survive an IP change without invalidating all existing user configs. Note: the WS transport `host` parameter in generated URIs is **intentionally the SNI value** (e.g. `m.zoom.us`), not the domain — this is a distinct, deliberate parameter and should not be conflated with the connection hostname.

4. **Cloudflare WARP as selective outbound** — sing-box routes specific domains (Netflix, Instagram, Facebook, etc.) through a local WARP SOCKS proxy (`127.0.0.1:40000`) to bypass geographic/service blocks, while everything else (including gaming traffic) goes `direct`. This was necessary because routing gaming traffic through WARP caused disconnects (UDP instability). Route config lives in `/etc/sing-box/config.json`.

5. **Filament (Laravel) is the intended permanent admin panel; `admin.html` is legacy** — a significant scope-drift incident occurred where an AI agent (during a "continue" prompt with lost context) built substantial new features into the legacy `admin.html` instead of Filament. That work is functional and committed, but the explicit direction is: **do not add further features to `admin.html`; port needed functionality into Filament and retire the legacy panel** (Phase 9 task).

---

## 6. Phase-by-Phase Status

| Phase | Description | Status |
|---|---|---|
| 1 | Laravel + Breeze auth (register, login, email verify, password reset) | ✅ Done |
| 2 | Customer dashboard: device list, add/remove device (initially placeholder UUIDs) | ✅ Done |
| 3 | Per-device SNI selection (dropdown of 8 camouflage domains, editable after creation); real FastAPI backend integration replacing placeholder UUIDs | ✅ Done |
| 4 | Domain used instead of raw IP in all generated connection URIs (both Laravel and FastAPI sides) | ✅ Done |
| 5 | Filament v4 admin panel: User & VpnDevice resources, stats widgets (total users, near-cap users, recent registrations), admin-only access via `is_admin` column + `canAccessPanel()` | ✅ Done |
| 5.5 | Refactored to one-FastAPI-user-per-customer, many-devices-per-user architecture (see §5.2) | ✅ Done |
| 6 | Real data usage tracking: `traffic_accumulator.py` polls sing-box Clash API → writes `/var/lib/zenvpn/traffic_stats.json` → FastAPI `GET /users/{username}/usage` → Laravel `vpn:sync-usage` artisan command (scheduled every 5 min) → updates `data_used_mb` on `users` and `vpn_devices` tables → displayed on dashboard + Filament | ✅ Done (for devices with a populated `last_ip` — see known limitation below) |
| 7 | Real SMTP email (currently `log` driver only), welcome email, 80%/100% data cap warning emails | 🔲 Not started |
| 8 | Bandwidth pool logic: reserved allocation for owner + trusted people (admin-adjustable), shared free pool with per-user cap, first-come-first-served enforcement when pool exhausted | 🔲 Not started — needs real design (likely `tc` Linux traffic control or sing-box per-inbound limits; no implementation attempted yet) |
| 9 | Terms of Service page, Google AdSense integration, retire `admin.html`, port any missing Filament functionality | 🔲 Not started |

---

## 7. Known Bugs Fixed (chronological, for context on fragility points)

- Stale Laravel user cache causing wrong FastAPI branch (`createUser` vs `addDevice`) — root cause was actually **orphaned FastAPI users from manual account deletion during testing**, not caching; fixed by deleting orphaned backend users to match Laravel's DB state.
- Malformed VPN URIs from `VpnDevice.php`'s local URI-builder methods — hardcoded wrong ports (443 instead of 4443/8443/80), wrong transport type (`tcp` instead of `ws`), missing `host`/`path` params. Fixed to match FastAPI's actual URI format exactly.
- `SERVER_IP` hardcoded in FastAPI's `generate_uris()` — added separate `SERVER_DOMAIN` constant, used only for user-facing URIs, left `SERVER_IP` intact for internal/diagnostic use.
- Filament v4 namespace migration issues — `Filament\Forms\Form` → `Filament\Schemas\Schema`; `Filament\Tables\Actions\*` → `Filament\Actions\*`; `Filament\Forms\Components\Section` → `Filament\Schemas\Components\Section`. **Any new Filament code should be double-checked against the actually-installed Filament version's real namespaces, not assumed from older tutorials/training data.**
- `SyncVpnUsage.php` usage-parsing bug — FastAPI returns `devices` as a numerically-indexed array of objects (`[{device, used_mb}, ...]`), but the sync command's `foreach` treated it as an associative array (`device_identifier => used_mb`), causing PHP to silently cast the whole sub-array to integer `1` (a classic PHP array-to-int gotcha). Fixed to properly destructure each element.
- `device_tracker.py` reading the entire (500MB+) log file every 30-second cycle, causing sustained ~25% CPU usage — fixed with offset-based tailing (seek to last-read position, only read new bytes).
- `device_tracker.py` `UnicodeDecodeError` crash-looping (34,000+ occurrences) when `seek()` landed mid-multibyte-character in the log file — fixed by reading in binary mode (`'rb'`) and manually decoding with `errors='replace'` afterward, rather than relying on text-mode `open()` with `errors='replace'` (which was insufficient).

---

## 8. Known Limitation — NOT YET SOLVED

**Per-device live IP / "last seen" tracking does not work for successfully-authenticated connections.**

Investigated thoroughly: sing-box only logs a connecting UUID when authentication **fails** (e.g. "unknown UUID" errors) — never on successful connections. The Clash API (`127.0.0.1:9090/connections`) exposes IP/host/protocol-type for live connections but does **not** expose which UUID/user owns each connection. This means `device_tracker.py`'s `last_ip`/`last_seen` fields stay empty indefinitely for legitimately connected devices, even while `traffic_accumulator.py` (which correlates by IP session, not UUID) continues to successfully track aggregate bandwidth.

**Investigated alternatives (none implemented):**
- Per-user unique sing-box inbounds (1 port per user) — technically works but doesn't scale to many users.
- Packet capture (tcpdump/eBPF) to catch the handshake and map IP→UUID — heavy, fragile, needs elevated privileges.
- Verbose/debug sing-box logging — likely still doesn't expose UUID on success path; not confirmed either way.

**Recommendation for next work on this:** Before attempting a fix, re-confirm whether a newer sing-box version has added structured per-user stats to its experimental API (this was last checked mid-2026 against the then-installed version). If not, the unique-inbound-per-user approach is probably the most realistic path, accepting the scaling tradeoff, or accept the limitation permanently and remove the "last seen" UI element rather than continue chasing it.

---

## 9. Plans / Pricing (for context, not final)

The pricing model has changed multiple times during planning. Most recent direction: **free service, ad-monetized**, replacing an earlier paid-tier plan (Basic 200 LKR/50Mbps/2 devices, Pro 500 LKR/100Mbps/5 devices via PayHere). PayHere integration was **never implemented** — explicitly deprioritized in favor of the free+ads model. If reviving paid tiers later, the `plan`/`bandwidth_mbps`/`device_limit` fields already exist on both the FastAPI `VPNUser` model and Laravel `users` table and can be repurposed.

Default device limit: 3 for regular users (admin-adjustable per user, `0` = unlimited, used for the owner's own account). Default data cap: 50GB/month for free users (`data_cap_mb`, `0` = unlimited).

---

## 10. Immediate Next Steps (suggested order)

1. **Verification task for new local agent setup:** Have the Planner model read this document plus the actual current state of both repos (`git log`, `git status`, current file contents) and confirm nothing has drifted since this document was written, before any new work begins.
2. Sync `/etc/sing-box/*` fixes into `/etc/zenvpn/singbox/*` copy if not already done (check git log for the most recent backend commits to confirm).
3. Decide whether to pursue Phase 7 (email), Phase 8 (bandwidth pool — needs design work first, not just implementation), or Phase 9 (launch polish) next.
4. Formally decide the fate of the "last seen" known limitation (§8) — fix, redesign, or remove from UI.

---

## 11. Tooling Notes for Local Model Handoff

- Previous development used Google Antigravity (Gemini-based agentic IDE) for planning/investigation, with execution sometimes handed off manually to `ollama launch claude --model gemma4:31b-cloud` (Google's Gemma4 model, run through the Claude Code CLI shell, unrelated to Anthropic despite the command name) to conserve Antigravity's rate-limited quota.
- **Observed reliability issue:** the Gemma4 setup twice reported changes as "applied" when the file on disk was actually unchanged. Any agent handoff should end with independent verification (re-reading the file, not trusting a summary) before considering a task done.
- Antigravity has browser-automation ("subagent") capability for visual UI verification that consumed noticeable quota; local models via OpenCode/LM Studio will not have this — manual browser testing by the human operator is expected to continue.
- Mixing a local folder (Windows Laravel project) and a remote SSH folder (VPS) in one IDE workspace caused connection instability in Antigravity — keep local (Laravel) and remote (VPS/backend) as separate agent sessions/workspaces.
