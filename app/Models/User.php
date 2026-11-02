<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    // role and is_active are intentionally NOT mass-assignable (prevents privilege escalation).
    protected $fillable = ['name', 'email', 'phone', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => Role::class,
        'is_active' => 'boolean',
    ];

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && $this->role->can($permission);
    }

    public function isStaffOrAdmin(): bool
    {
        return in_array($this->role, [Role::Staff, Role::Admin], true);
    }

    public function addresses(): HasMany { return $this->hasMany(Address::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function supportTickets(): HasMany { return $this->hasMany(SupportTicket::class); }
}
