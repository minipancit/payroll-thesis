<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class TimeLog extends Model
{
    protected $fillable = [
        'user_id',
        'event_id',
        'user_latitude',
        'user_longitude',
        'is_within_radius',
        'distance_from_event',
        'time_in',
        'time_out',
    ];

    protected $casts = [
        'user_latitude' => 'decimal:8',
        'user_longitude' => 'decimal:8',
        'distance_from_event' => 'decimal:2',
        'is_within_radius' => 'boolean',
        'time_in' => 'datetime',
        'time_out' => 'datetime',
    ];

    /**
     * Get the user that owns the time log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the event that owns the time log.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the duration in minutes.
     */
    public function getDurationMinutesAttribute(): ?int
    {
        if (!$this->time_in || !$this->time_out) {
            return null;
        }
        
        return Carbon::parse($this->time_in)->diffInMinutes($this->time_out);
    }

    /**
     * Get the duration in hours.
     */
    public function getDurationHoursAttribute(): ?float
    {
        if (!$this->time_in || !$this->time_out) {
            return null;
        }
        
        return round($this->duration_minutes / 60, 2);
    }

    /**
     * Get formatted duration.
     */
    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->time_in || !$this->time_out) {
            return null;
        }
        
        $minutes = $this->duration_minutes;
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return sprintf("%dh %02dm", $hours, $remainingMinutes);
    }

    /**
     * Check if time log is active (no time out).
     */
    public function getIsActiveAttribute(): bool
    {
        return is_null($this->time_out);
    }

    /**
     * Get user coordinates as array.
     */
    public function getUserCoordinatesAttribute(): array
    {
        return [
            'latitude' => $this->user_latitude,
            'longitude' => $this->user_longitude,
        ];
    }

    /**
     * Get distance formatted.
     */
    public function getDistanceFormattedAttribute(): string
    {
        return \App\Helpers\GeoHelper::formatDistance($this->distance_from_event);
    }

    /**
     * Scope active time logs.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('time_out');
    }

    /**
     * Scope time logs within radius.
     */
    public function scopeWithinRadius($query)
    {
        return $query->where('is_within_radius', true);
    }

    /**
     * Scope time logs for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('time_in', today());
    }

    /**
     * Scope time logs for a specific user and event.
     */
    public function scopeForUserAndEvent($query, $userId, $eventId)
    {
        return $query->where('user_id', $userId)->where('event_id', $eventId);
    }
}