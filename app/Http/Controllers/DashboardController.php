<?php

namespace App\Http\Controllers;

use App\Models\DailyTimeRecord;
use App\Models\Event;
use App\Models\LoginAttempt;
use App\Models\TimeLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $eventsToday = Event::today()
            ->orderBy('start_time')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'formatted_date' => $event->formatted_date,
                    'formatted_time_range' => $event->formatted_time_range,
                    'status' => $event->status,
                ];
            });

        $upcomingEvents = Event::upcoming()
            ->take(4)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'formatted_date' => $event->formatted_date,
                    'formatted_time_range' => $event->formatted_time_range,
                    'status' => $event->status,
                ];
            });

        $activeTimeLogs = LoginAttempt::with(['user', 'event'])
            ->where('status', 'success')
            ->whereDate('authenticated_at', Carbon::today())
            ->whereNotExists(function ($query) {
                $query->selectRaw(1)
                    ->from('login_attempts as la2')
                    ->whereColumn('la2.user_id', 'login_attempts.user_id')
                    ->whereColumn('la2.event_id', 'login_attempts.event_id')
                    ->whereDate('la2.authenticated_at', Carbon::today())
                    ->where('la2.status', 'success')
                    ->whereColumn('la2.authenticated_at', '>', 'login_attempts.authenticated_at');
            })
            ->latest('authenticated_at')
            ->take(6)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user?->name,
                    'event_name' => $log->event?->name,
                    'time_in' => optional($log->authenticated_at)->format('H:i'),
                    'created_at' => optional($log->created_at)->format('Y-m-d H:i'),
                ];
            });

        $recentLogs = LoginAttempt::with(['user', 'event'])
            ->where('status', 'success')
            ->latest('authenticated_at')
            ->take(8)
            ->get()
            ->map(function ($log) {
                // Determine if this is time in or time out based on position
                $userAttemptsToday = LoginAttempt::where('user_id', $log->user_id)
                    ->where('event_id', $log->event_id)
                    ->where('status', 'success')
                    ->whereDate('authenticated_at', $log->authenticated_at->toDateString())
                    ->orderBy('authenticated_at')
                    ->pluck('id');

                $isFirst = $userAttemptsToday->first() === $log->id;
                $isLast = $userAttemptsToday->last() === $log->id;

                $type = 'Login';
                if ($isFirst && $isLast) {
                    $type = 'Login (Single)';
                } elseif ($isFirst) {
                    $type = 'Time In';
                } elseif ($isLast) {
                    $type = 'Time Out';
                }

                return [
                    'id' => $log->id,
                    'user_name' => $log->user?->name,
                    'event_name' => $log->event?->name,
                    'type' => $type,
                    'time' => optional($log->authenticated_at)->format('H:i'),
                    'date' => optional($log->authenticated_at)->format('Y-m-d'),
                ];
            });

        $eventCounts = [
            'total' => Event::count(),
            'today' => Event::today()->count(),
            'upcoming' => Event::upcoming()->count(),
            'active' => Event::active()->count(),
        ];

        $dtrSummary = [
            'total_records' => DailyTimeRecord::count(),
            'completed' => DailyTimeRecord::completed()->count(),
            'late' => DailyTimeRecord::late()->count(),
            'absent' => DailyTimeRecord::whereNull('actual_time_in')->count(),
            'today' => DailyTimeRecord::today()->count(),
        ];


        return Inertia::render('Dashboard', [
            'eventCounts' => $eventCounts,
            'eventsToday' => $eventsToday,
            'upcomingEvents' => $upcomingEvents,
            'activeLogs' => $activeTimeLogs,
            'recentLogs' => $recentLogs,
            'dtrSummary' => $dtrSummary,
        ]);
    }
}
