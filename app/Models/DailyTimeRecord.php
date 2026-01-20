<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class DailyTimeRecord extends Model
{
    protected $fillable = [
        'user_id',
        'event_id',
        'log_date',
        'scheduled_time_in',
        'scheduled_time_out',
        'actual_time_in',
        'actual_time_out',
        'late_minutes',
        'overtime_minutes',
        'undertime_minutes',
        'total_hours',
        'regular_hours',
        'overtime_hours',
        'time_log_count',
        'time_logs_summary',
        'remarks',
    ];

    protected $casts = [
        'log_date' => 'date',
        'scheduled_time_in' => 'datetime',
        'scheduled_time_out' => 'datetime',
        'actual_time_in' => 'datetime',
        'actual_time_out' => 'datetime',
        'total_hours' => 'decimal:2',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'time_logs_summary' => 'array',
    ];

    /**
     * Get the user that owns the DTR.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the event that owns the DTR.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get formatted late time.
     */
    public function getLateFormattedAttribute(): string
    {
        if ($this->late_minutes <= 0) {
            return 'On Time';
        }
        
        $hours = floor($this->late_minutes / 60);
        $minutes = $this->late_minutes % 60;
        
        if ($hours > 0) {
            return sprintf("%dh %02dm late", $hours, $minutes);
        }
        
        return sprintf("%dm late", $minutes);
    }

    /**
     * Get formatted overtime.
     */
    public function getOvertimeFormattedAttribute(): string
    {
        if ($this->overtime_minutes <= 0) {
            return 'No OT';
        }
        
        $hours = floor($this->overtime_minutes / 60);
        $minutes = $this->overtime_minutes % 60;
        
        if ($hours > 0) {
            return sprintf("%dh %02dm OT", $hours, $minutes);
        }
        
        return sprintf("%dm OT", $minutes);
    }

    /**
     * Get formatted undertime.
     */
    public function getUndertimeFormattedAttribute(): string
    {
        if ($this->undertime_minutes <= 0) {
            return 'Complete';
        }
        
        $hours = floor($this->undertime_minutes / 60);
        $minutes = $this->undertime_minutes % 60;
        
        if ($hours > 0) {
            return sprintf("%dh %02dm UT", $hours, $minutes);
        }
        
        return sprintf("%dm UT", $minutes);
    }

    /**
     * Check if DTR is completed.
     */
    public function getIsCompletedAttribute(): bool
    {
        return !is_null($this->actual_time_in) && !is_null($this->actual_time_out);
    }

    /**
     * Check if DTR has overtime.
     */
    public function getHasOvertimeAttribute(): bool
    {
        return $this->overtime_minutes >= 30; // Minimum 30 minutes for overtime
    }

    /**
     * Check if DTR is late.
     */
    public function getIsLateAttribute(): bool
    {
        return $this->late_minutes > 0;
    }

    /**
     * Get status of DTR.
     */
    public function getStatusAttribute(): string
    {
        if (!$this->actual_time_in && !$this->actual_time_out) {
            return 'Absent';
        }
        
        if ($this->actual_time_in && !$this->actual_time_out) {
            return 'On Duty';
        }
        
        if ($this->is_completed) {
            if ($this->has_overtime) {
                return 'Completed with OT';
            }
            
            if ($this->undertime_minutes > 0) {
                return 'Completed with UT';
            }
            
            if ($this->late_minutes > 0) {
                return 'Completed (Late)';
            }
            
            return 'Completed';
        }
        
        return 'Pending';
    }

    /**
     * Get total work hours formatted.
     */
    public function getTotalHoursFormattedAttribute(): string
    {
        if (!$this->total_hours) {
            return '0h';
        }
        
        $hours = floor($this->total_hours);
        $minutes = round(($this->total_hours - $hours) * 60);
        
        if ($hours > 0 && $minutes > 0) {
            return sprintf("%dh %02dm", $hours, $minutes);
        } elseif ($hours > 0) {
            return sprintf("%dh", $hours);
        } else {
            return sprintf("%dm", $minutes);
        }
    }

    /**
     * Scope completed DTRs.
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('actual_time_in')->whereNotNull('actual_time_out');
    }

    /**
     * Scope DTRs with overtime.
     */
    public function scopeWithOvertime($query)
    {
        return $query->where('overtime_minutes', '>=', 30);
    }

    /**
     * Scope DTRs that are late.
     */
    public function scopeLate($query)
    {
        return $query->where('late_minutes', '>', 0);
    }

    /**
     * Scope DTRs for a specific date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('log_date', [$startDate, $endDate]);
    }

    /**
     * Scope today's DTR.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('log_date', today());
    }
}