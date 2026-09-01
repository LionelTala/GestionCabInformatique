<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'role',
        'campus_id',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Relation avec Campus
    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    // Vérification des rôles
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdminGlobal(): bool
    {
        return $this->role === 'admin_global';
    }

    public function isAdminCampus(): bool
    {
        return $this->role === 'admin_campus';
    }

    public function isSecretary(): bool
    {
        return $this->role === 'secretary';
    }

    public function hasAccessToCampus($campusId): bool
    {
        if ($this->isSuperAdmin() || $this->isAdminGlobal()) {
            return true;
        }
        return $this->campus_id === $campusId;
    }
}