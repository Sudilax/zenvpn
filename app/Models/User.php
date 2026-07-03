<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'device_limit', 'data_used_mb', 'data_cap_mb', 'is_admin', 'status', 'fastapi_username'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Only is_admin = true users may access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'device_limit'      => 'integer',
            'data_used_mb'      => 'integer',
            'data_cap_mb'       => 'integer',
            'is_admin'          => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function vpnDevices(): HasMany
    {
        return $this->hasMany(VpnDevice::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Whether this user can add another device */
    public function canAddDevice(): bool
    {
        if ($this->device_limit === 0) {
            return true;
        }
        return $this->vpnDevices()->count() < $this->device_limit;
    }

    /** Data used as GB, rounded to 2 decimal places */
    public function dataUsedGb(): float
    {
        return round($this->data_used_mb / 1024, 2);
    }

    /** Data cap as GB */
    public function dataCapGb(): float
    {
        return round($this->data_cap_mb / 1024, 2);
    }

    /** Data usage percentage (0–100) */
    public function dataUsagePercent(): int
    {
        if ($this->data_cap_mb === 0) {
            return 0;
        }
        return (int) min(100, round(($this->data_used_mb / $this->data_cap_mb) * 100));
    }
}
