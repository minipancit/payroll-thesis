<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLocation extends Model
{
    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'accuracy',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];
}