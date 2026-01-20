<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class Event extends Model
{
    protected $fillable = [
        'name',
        'address',
        'event_date',
        'start_time',
        'end_time',
        'latitude',
        'longitude',
        'description',
    ];

    protected $casts = [
        'event_date' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];
    // Remove 'start_time' => 'datetime', 'end_time' => 'datetime' from casts

    protected $appends = [
        'formatted_date',
        'formatted_time_range',
        'status',
        'is_active',
        'is_past',
        'is_future',
        'is_today',
        'days_until'
    ];
    /**
     * Get the time logs for the event.
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    /**
     * Get the daily time records for the event.
     */
    public function dailyTimeRecords(): HasMany
    {
        return $this->hasMany(DailyTimeRecord::class);
    }

    /**
     * Get the full event datetime start.
     */
    public function getStartDatetimeAttribute(): ?Carbon
    {
        if (!$this->event_date || !$this->start_time) {
            return null;
        }
        
        return Carbon::parse($this->event_date->format('Y-m-d') . ' ' . $this->start_time);
    }

    /**
     * Get the full event datetime end.
     */
    public function getEndDatetimeAttribute(): ?Carbon
    {
        if (!$this->event_date || !$this->end_time) {
            return null;
        }
        
        return Carbon::parse($this->event_date->format('Y-m-d') . ' ' . $this->end_time);
    }

    /**
     * Get formatted start time.
     */
    public function getFormattedStartTimeAttribute(): ?string
    {
        if (!$this->start_time) {
            return null;
        }
        
        return Carbon::parse($this->start_time)->format('g:i A');
    }

    /**
     * Get formatted end time.
     */
    public function getFormattedEndTimeAttribute(): ?string
    {
        if (!$this->end_time) {
            return null;
        }
        
        return Carbon::parse($this->end_time)->format('g:i A');
    }

    /**
     * Get the latitude in DMS format.
     */
    public function getLatitudeDmsAttribute(): string
    {
        if (!$this->latitude) return '';
        return \App\Helpers\GeoHelper::decimalToDMS($this->latitude, true);
    }

    /**
     * Get the longitude in DMS format.
     */
    public function getLongitudeDmsAttribute(): string
    {
        if (!$this->longitude) return '';
        return \App\Helpers\GeoHelper::decimalToDMS($this->longitude, false);
    }

    /**
     * Check if location is set.
     */
    public function getHasLocationAttribute(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Check if event is happening today.
     */
    public function getIsTodayAttribute()
    {
        return Carbon::parse($this->event_date)->isSameDay(Carbon::today());
    }


    /**
     * Get days until event.
     */
    public function getDaysUntilAttribute()
    {
        $eventDate = Carbon::parse($this->event_date);
        $today = Carbon::today();
        
        return $eventDate->diffInDays($today);
    }

    /**
     * Check if event is in the future.
     */
    public function getIsFutureAttribute()
    {
        $now = Carbon::now();
        $today = Carbon::today();
        $eventDate = Carbon::parse($this->event_date);
        $startTime = Carbon::parse($this->start_time);

        if ($eventDate->isFuture() && !$eventDate->isSameDay($today)) {
            return true;
        }

        return $eventDate->isSameDay($today) && 
               $now->lessThan($startTime);
    }

    /**
     * Check if event is in the past.
     */
    public function getIsPastAttribute()
    {
        $now = Carbon::now();
        $today = Carbon::today();
        $eventDate = Carbon::parse($this->event_date);
        $endTime = $this->end_time ? Carbon::parse($this->end_time) : null;

        if ($eventDate->isPast() && !$eventDate->isSameDay($today)) {
            return true;
        }

        return $eventDate->isSameDay($today) && 
               $endTime && 
               $now->greaterThan($endTime);
    }


    /**
     * Check if event is currently active (happening now).
     */
    public function getIsActiveAttribute()
    {
        $now = Carbon::now();
        $today = Carbon::today();
        $eventDate = Carbon::parse($this->event_date);
        $startTime = Carbon::parse($this->start_time);
        $endTime = $this->end_time ? Carbon::parse($this->end_time) : null;

        return $eventDate->isSameDay($today) && 
               $now->greaterThanOrEqualTo($startTime) && 
               (!$endTime || $now->lessThanOrEqualTo($endTime));
    }

    /**
     * Get formatted event date.
     */
    public function getFormattedDateAttribute(): string
    {
        return Carbon::parse($this->event_date)->format('F j, Y');
    }

    /**
     * Get formatted time range.
     */
    public function getFormattedTimeRangeAttribute()
    {
        $start = Carbon::parse($this->start_time)->format('g:i A');
        $end = $this->end_time ? Carbon::parse($this->end_time)->format('g:i A') : null;
        
        return $end ? "{$start} - {$end}" : "{$start} onwards";
    }

    /**
     * Get event status.
     */
    public function getStatusAttribute()
    {
        $now = Carbon::now();
        $today = Carbon::today();
        $eventDate = Carbon::parse($this->event_date);
        $startTime = Carbon::parse($this->start_time);
        $endTime = $this->end_time ? Carbon::parse($this->end_time) : null;

        if ($eventDate->isSameDay($today)) {
            if ($now->lessThan($startTime)) {
                return 'Upcoming Today';
            } elseif ($endTime && $now->greaterThan($endTime)) {
                return 'Completed';
            } elseif ($now->between($startTime, $endTime)) {
                return 'Active Now';
            } else {
                return 'Scheduled';
            }
        } elseif ($eventDate->isFuture()) {
            return 'Upcoming';
        } else {
            return 'Past';
        }
    }

    /**
     * Get coordinates as array.
     */
    public function getCoordinatesAttribute(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'latitude_dms' => $this->latitude_dms,
            'longitude_dms' => $this->longitude_dms,
        ];
    }

    /**
     * Scope events for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('event_date', today());
    }

    /**
     * Scope upcoming events.
     */
    public function scopeUpcoming($query)
    {
        return $query->whereDate('event_date', '>=', today())
            ->orderBy('event_date')
            ->orderBy('start_time');
    }

    /**
     * Scope past events.
     */
    public function scopePast($query)
    {
        return $query->whereDate('event_date', '<', today())
            ->orWhere(function ($q) {
                $q->whereDate('event_date', today())
                    ->where('end_time', '<', now()->format('H:i:s'));
            })
            ->orderBy('event_date', 'desc')
            ->orderBy('end_time', 'desc');
    }

    /**
     * Scope active events (happening now).
     */
    public function scopeActive($query)
    {
        $today = today()->format('Y-m-d');
        $now = now()->format('H:i:s');
        
        return $query->where('event_date', $today)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now);
    }

    /**
     * Scope events with location.
     */
    public function scopeWithLocation($query)
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    /**
     * Scope events for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('event_date', $date);
    }

    /**
     * Scope events between dates.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('event_date', [$startDate, $endDate]);
    }

    /**
     * Scope events near a location.
     */
    public function scopeNear($query, $latitude, $longitude, $radius = 5000)
    {
        $haversine = "(6371000 * acos(cos(radians(?)) 
            * cos(radians(latitude)) 
            * cos(radians(longitude) - radians(?)) 
            + sin(radians(?)) 
            * sin(radians(latitude))))";

        return $query
            ->select('*')
            ->selectRaw("{$haversine} AS distance", [$latitude, $longitude, $latitude])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->having('distance', '<=', $radius)
            ->orderBy('distance');
    }

    /**
     * Mutator for start_time to ensure proper format
     */
    public function setStartTimeAttribute($value)
    {
        if ($value) {
            $this->attributes['start_time'] = Carbon::parse($value)->format('H:i:s');
        } else {
            $this->attributes['start_time'] = null;
        }
    }

    /**
     * Mutator for end_time to ensure proper format
     */
    public function setEndTimeAttribute($value)
    {
        if ($value) {
            $this->attributes['end_time'] = Carbon::parse($value)->format('H:i:s');
        } else {
            $this->attributes['end_time'] = null;
        }
    }
}