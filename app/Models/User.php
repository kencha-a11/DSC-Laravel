<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active_status',
        'account_status',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sales()
    {
        return $this->hasMany(\App\Models\Sale::class);
    }

    /**
     * User has many time logs
     */
    public function timeLogs()
    {
        return $this->hasMany(\App\Models\TimeLog::class);
    }

    /**
     * User has many sales logs
     */
    public function salesLogs()
    {
        return $this->hasMany(\App\Models\SalesLog::class);
    }

    /**
     * User has many inventory logs
     */
    public function inventoryLogs()
    {
        return $this->hasMany(\App\Models\InventoryLog::class);
    }
}
