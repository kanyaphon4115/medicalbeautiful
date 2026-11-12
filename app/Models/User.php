<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_USER = 'user';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_HOSPITAL = 'hospital';
    public const ROLE_SUPPORT = 'support';
    protected $attributes = ['role'=>self::ROLE_USER];

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPassword($token));
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_id' => 'encrypted',
            'date_of_birth' => 'encrypted',
            'gender' => 'encrypted',
            'line_id' => 'encrypted',
            'date_of_birth' => 'encrypted',
            'gender' => 'encrypted',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
            'account_deletion_requested_at' => 'datetime',
        ];
    }
    public function isChatSupport(): bool
    {
        return $this->isAdmin() || $this->role === self::ROLE_SUPPORT;
    }
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isHospital(): bool
    {
        return $this->role === self::ROLE_HOSPITAL;
    }

    public function landingRoute(): string
    {
        return $this->role===self::ROLE_SUPPORT ? 'admin.support' : ($this->isHospital() ? 'hospital.dashboard' : 'home');
    }



    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_HOSPITAL => 'Hospital',
            self::ROLE_SUPPORT => 'Customer Support',
            default => 'Customer',
        };
    }
}
