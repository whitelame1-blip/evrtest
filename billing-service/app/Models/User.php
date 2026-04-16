<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Paddle\Billable; // 1. Import Paddle Billable trait

class User extends Authenticatable
{
    use HasFactory, Notifiable, Billable; // 2. Add Billable here

    /**
     * The attributes that are mass assignable.
     * * UPDATED to include your specific database columns
     */
    public $timestamps = false;
    
    protected $fillable = [
        'fullname',   // Matches your manual script
        'email',
        'password',
        'google_id',  // Required for Google Login
        'verified',
        'verification_code',
        'reset_token',   // Add this
        'reset_expires',
        'download_attempts'
       
    ];
    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'verified' => 'boolean', // Casts 1/0 to true/false
        ];
    }
}