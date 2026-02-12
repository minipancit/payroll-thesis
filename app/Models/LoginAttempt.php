<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'face_data_hash',
        'latitude',
        'longitude',
        'device_info',
        'attempted_at',
        'authenticated_at',
        'ip_address',
        'user_agent',
        'status',
        'failure_reason',
    ];

    protected $casts = [
        'device_info' => 'array',
        'attempted_at' => 'datetime',
        'authenticated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}