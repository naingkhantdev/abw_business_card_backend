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
        'name',
        'email',
        'password',
        'otp',
        'otp_expires_at',
        'email_verified_at',
        'is_verified',
        'deactivated_at',
        'reactivation_deadline_at',
        'fcm_token',
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'reactivation_deadline_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function businessCard()
    {
        return $this->hasOne(BusinessCard::class);
    }

    public function businessCards()
    {
        return $this->hasMany(BusinessCard::class);
    }

    public function sentFriendRequests()
    {
        return $this->hasMany(Friendship::class, 'requester_user_id');
    }

    public function receivedFriendRequests()
    {
        return $this->hasMany(Friendship::class, 'receiver_user_id');
    }
}
