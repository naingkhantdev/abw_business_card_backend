<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessCard extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'company_id',
        'name',
        'position',
        'phones',
        'emails',
        'addresses',
        'bio',
        'profile_image',
        'front_image',
        'back_image',
        'card_type',
        'qr_code_data',
        'social_links',
    ];

    protected $casts = [
        'phones' => 'array',
        'emails' => 'array',
        'addresses' => 'array',
        'social_links' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Auto-stamp created_by / updated_by / deleted_by.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($card) {
            if (auth()->check()) {
                $card->created_by = $card->created_by ?? auth()->id();
            }
        });

        static::updating(function ($card) {
            if (auth()->check()) {
                $card->updated_by = auth()->id();
            }
        });

        static::deleting(function ($card) {
            if (auth()->check() && $card->isForceDeleting() === false) {
                $card->deleted_by = auth()->id();
                $card->saveQuietly(); // save the deleted_by without triggering events again
            }
        });
    }
}
