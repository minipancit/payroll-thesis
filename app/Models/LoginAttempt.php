<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'face_data_hash',
        'status',
        'failure_reason',
        'latitude',
        'longitude',
        'device_info',
        'ip_address',
        'user_agent',
        'confidence_score',
        'attempted_at',
        'authenticated_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'confidence_score' => 'float',
        'attempted_at' => 'datetime',
        'authenticated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRecent($query, $minutes = 15)
    {
        return $query->where('attempted_at', '>=', now()->subMinutes($minutes));
    }
}