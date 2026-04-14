<?php

namespace App\Services;

use App\Models\DailyTimeRecord;
use App\Models\LoginAttempt;
use App\Models\TimeLog;
use Carbon\Carbon;

class DTRService
{
    // Configuration
    const REGULAR_WORKING_HOURS = 8; // 8 hours regular work
    const OVERTIME_THRESHOLD_MINUTES = 30; // Minimum 30 mins for overtime
    const LATE_THRESHOLD_MINUTES = 15; // Grace period for late
    const LUNCH_BREAK_MINUTES = 60; // 1 hour lunch break

    /**
     * Process login attempt and update DTR
     * First successful login = Time In, Last successful login = Time Out
     */
    public function processLoginAttempt(LoginAttempt $loginAttempt): DailyTimeRecord
    {
        $userId = $loginAttempt->user_id;
        $eventId = $loginAttempt->event_id;
        $today = Carbon::parse($loginAttempt->authenticated_at)->toDateString();

        // Get or create DTR for today
        $dtr = DailyTimeRecord::firstOrCreate(
            [
                'user_id' => $userId,
                'event_id' => $eventId,
                'log_date' => $today,
            ]
        );

        // Get all successful login attempts for this user/event today
        $todayLoginAttempts = LoginAttempt::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->where('status', 'success')
            ->whereDate('authenticated_at', $today)
            ->orderBy('authenticated_at')
            ->get();

        if ($todayLoginAttempts->isEmpty()) {
            return $dtr;
        }

        // First login attempt = Time In
        $firstLogin = $todayLoginAttempts->first();
        $dtr->actual_time_in = Carbon::parse($firstLogin->authenticated_at)->format('H:i:s');

        // Calculate late minutes if scheduled time is set
        if ($dtr->scheduled_time_in) {
            $scheduledTime = Carbon::parse($dtr->scheduled_time_in);
            $actualTime = Carbon::parse($firstLogin->authenticated_at);

            $lateMinutes = max(0, $actualTime->diffInMinutes($scheduledTime, false));
            if ($lateMinutes > self::LATE_THRESHOLD_MINUTES) {
                $dtr->late_minutes = $lateMinutes - self::LATE_THRESHOLD_MINUTES;
            }
        }

        // Last login attempt = Time Out (if there are multiple)
        if ($todayLoginAttempts->count() > 1) {
            $lastLogin = $todayLoginAttempts->last();
            $dtr->actual_time_out = Carbon::parse($lastLogin->authenticated_at)->format('H:i:s');

            // Calculate total hours and overtime
            $this->calculateHoursAndOvertime($dtr);
        }

        // Update login attempt count
        $dtr->time_log_count = $todayLoginAttempts->count();

        $dtr->save();

        return $dtr;
    }

    /**
     * Process all login attempts for a user/event on a specific date
     */
    public function processDailyLoginAttempts(int $userId, int $eventId, string $date): DailyTimeRecord
    {
        // Get all successful login attempts for this user/event/date
        $loginAttempts = LoginAttempt::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->where('status', 'success')
            ->whereDate('authenticated_at', $date)
            ->orderBy('authenticated_at')
            ->get();

        if ($loginAttempts->isEmpty()) {
            // Return existing DTR or create empty one
            return DailyTimeRecord::firstOrCreate(
                [
                    'user_id' => $userId,
                    'event_id' => $eventId,
                    'log_date' => $date,
                ]
            );
        }

        // Get or create DTR for the date
        $dtr = DailyTimeRecord::firstOrCreate(
            [
                'user_id' => $userId,
                'event_id' => $eventId,
                'log_date' => $date,
            ]
        );

        // First login attempt = Time In
        $firstLogin = $loginAttempts->first();
        $dtr->actual_time_in = $firstLogin->authenticated_at;

        // Calculate late minutes if scheduled time is set
        if ($dtr->scheduled_time_in) {
            $scheduledTime = Carbon::parse($dtr->scheduled_time_in);
            $actualTime = Carbon::parse($firstLogin->authenticated_at);

            $lateMinutes = max(0, $actualTime->diffInMinutes($scheduledTime, false));
            if ($lateMinutes > self::LATE_THRESHOLD_MINUTES) {
                $dtr->late_minutes = $lateMinutes - self::LATE_THRESHOLD_MINUTES;
            } else {
                $dtr->late_minutes = 0;
            }
        }

        // Last login attempt = Time Out (if there are multiple)
        if ($loginAttempts->count() > 1) {
            $lastLogin = $loginAttempts->last();
            $dtr->actual_time_out = $lastLogin->authenticated_at;

            // Calculate total hours and overtime
            $this->calculateHoursAndOvertime($dtr);
        }

        // Update login attempt count
        $dtr->time_log_count = $loginAttempts->count();

        $dtr->save();

        return $dtr;
    }
    public function processTimeLog(TimeLog $timeLog, bool $isTimeOut = false): DailyTimeRecord
    {
        $userId = $timeLog->user_id;
        $eventId = $timeLog->event_id;
        $today = Carbon::today()->toDateString();

        // Get or create DTR for today
        $dtr = DailyTimeRecord::firstOrCreate(
            [
                'user_id' => $userId,
                'event_id' => $eventId,
                'log_date' => $today,
            ]
        );

        // If this is the first time-in for the day and within radius
        if (!$isTimeOut && !$dtr->actual_time_in && $timeLog->is_within_radius) {
            $dtr->actual_time_in = $timeLog->time_in;

            // Calculate late minutes if scheduled time is set
            if ($dtr->scheduled_time_in) {
                $scheduledTime = Carbon::parse($dtr->scheduled_time_in);
                $actualTime = Carbon::parse($timeLog->time_in);

                $lateMinutes = max(0, $actualTime->diffInMinutes($scheduledTime, false));
                if ($lateMinutes > self::LATE_THRESHOLD_MINUTES) {
                    $dtr->late_minutes = $lateMinutes - self::LATE_THRESHOLD_MINUTES;
                }
            }
        }

        // If time-out, check if this is the last time-out for the day
        if ($isTimeOut) {
            $lastTimeOut = TimeLog::where('user_id', $userId)
                ->where('event_id', $eventId)
                ->whereDate('time_out', $today)
                ->whereNotNull('time_out')
                ->orderBy('time_out', 'desc')
                ->first();

            if ($lastTimeOut && Carbon::parse($lastTimeOut->time_out)->gt(Carbon::parse($dtr->actual_time_out ?? '00:00:00'))) {
                $dtr->actual_time_out = $lastTimeOut->time_out;

                // Calculate total hours and overtime
                $this->calculateHoursAndOvertime($dtr);
            }
        }

        // Update time log count
        $dtr->time_log_count = TimeLog::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->whereDate('time_in', $today)
            ->count();

        $dtr->save();

        return $dtr;
    }
    
    /**
     * Calculate total hours, overtime, and undertime
     */
    private function calculateHoursAndOvertime(DailyTimeRecord $dtr): void
    {
        if (!$dtr->actual_time_in || !$dtr->actual_time_out) {
            return;
        }
        
        $timeIn = Carbon::parse($dtr->actual_time_in);
        $timeOut = Carbon::parse($dtr->actual_time_out);
        
        // Create lunch break times for the same date as time in
        $lunchStart = Carbon::parse($timeIn->format('Y-m-d') . ' 12:00:00');
        $lunchEnd = Carbon::parse($timeIn->format('Y-m-d') . ' 13:00:00');
        
        // Calculate total minutes worked
        $totalMinutes = $timeIn->diffInMinutes($timeOut);
        
        // Subtract lunch break if it falls within work hours
        if ($timeIn->lt($lunchEnd) && $timeOut->gt($lunchStart)) {
            $lunchMinutes = 0;
            
            if ($timeIn->lt($lunchStart) && $timeOut->gt($lunchEnd)) {
                $lunchMinutes = self::LUNCH_BREAK_MINUTES;
            } elseif ($timeIn->between($lunchStart, $lunchEnd) || $timeOut->between($lunchStart, $lunchEnd)) {
                $lunchStartTime = max($timeIn, $lunchStart);
                $lunchEndTime = min($timeOut, $lunchEnd);
                $lunchMinutes = $lunchStartTime->diffInMinutes($lunchEndTime);
            }
            
            $totalMinutes -= $lunchMinutes;
        }
        
        // Calculate total hours
        $totalHours = $totalMinutes / 60;
        $dtr->total_hours = round($totalHours, 2);
        
        // Calculate regular hours (capped at 8 hours)
        $regularHours = min(self::REGULAR_WORKING_HOURS, $totalHours);
        $dtr->regular_hours = round($regularHours, 2);
        
        // Calculate overtime (only if 30 minutes or more beyond 8 hours)
        $overtimeMinutes = max(0, $totalMinutes - (self::REGULAR_WORKING_HOURS * 60));
        
        if ($overtimeMinutes >= self::OVERTIME_THRESHOLD_MINUTES) {
            $dtr->overtime_minutes = $overtimeMinutes;
            $dtr->overtime_hours = round($overtimeMinutes / 60, 2);
        } else {
            $dtr->overtime_minutes = 0;
            $dtr->overtime_hours = 0;
        }
        
        // Calculate undertime (if worked less than 8 hours)
        $undertimeMinutes = max(0, (self::REGULAR_WORKING_HOURS * 60) - $totalMinutes);
        $dtr->undertime_minutes = $undertimeMinutes;
        
        // Calculate overtime pay if needed
        $this->calculateOvertimePay($dtr);
    }
    
    /**
     * Calculate overtime pay based on company policy
     */
    private function calculateOvertimePay(DailyTimeRecord $dtr): void
    {
        if ($dtr->overtime_minutes <= 0) {
            return;
        }
        
        // Example overtime calculation (adjust based on your requirements)
        $regularRate = 100; // Example hourly rate
        $overtimeRate = $regularRate * 1.25; // Time and a half for overtime
        
        $overtimeHours = $dtr->overtime_hours;
        $overtimePay = $overtimeHours * $overtimeRate;
        
        // You can store this in a separate table or add a field
        // $dtr->overtime_pay = $overtimePay;
    }
    
    /**
     * Set schedule for a user/event
     */
    public function setSchedule(int $userId, int $eventId, string $date, string $timeIn, string $timeOut): DailyTimeRecord
    {
        $dtr = DailyTimeRecord::updateOrCreate(
            [
                'user_id' => $userId,
                'event_id' => $eventId,
                'log_date' => $date,
            ],
            [
                'scheduled_time_in' => Carbon::parse($date . ' ' . $timeIn),
                'scheduled_time_out' => Carbon::parse($date . ' ' . $timeOut),
            ]
        );
        
        // Recalculate if actual times are already set
        if ($dtr->actual_time_in && $dtr->actual_time_out) {
            $this->calculateHoursAndOvertime($dtr);
        }
        
        return $dtr;
    }
    
    /**
     * Get overtime summary for a period
     */
    public function getOvertimeSummary(int $userId, string $startDate, string $endDate): array
    {
        return DailyTimeRecord::where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->where('overtime_minutes', '>=', self::OVERTIME_THRESHOLD_MINUTES)
            ->select('log_date', 'overtime_minutes', 'overtime_hours')
            ->orderBy('log_date')
            ->get()
            ->toArray();
    }
    
    /**
     * Get late summary for a period
     */
    public function getLateSummary(int $userId, string $startDate, string $endDate): array
    {
        return DailyTimeRecord::where('user_id', $userId)
            ->whereBetween('log_date', [$startDate, $endDate])
            ->where('late_minutes', '>', 0)
            ->select('log_date', 'late_minutes')
            ->orderBy('log_date')
            ->get()
            ->toArray();
    }
}