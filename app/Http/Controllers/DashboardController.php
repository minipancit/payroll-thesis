<?php

namespace App\Http\Controllers;

use App\Models\DailyTimeRecord;
use App\Models\Event;
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

        $activeTimeLogs = TimeLog::with(['user', 'event'])
            ->whereNull('time_out')
            ->latest('time_in')
            ->take(6)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user?->name,
                    'event_name' => $log->event?->name,
                    'time_in' => optional($log->time_in)->format('H:i'),
                    'created_at' => optional($log->created_at)->format('Y-m-d H:i'),
                ];
            });

        $recentLogs = TimeLog::with(['user', 'event'])
            ->latest('created_at')
            ->take(8)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user?->name,
                    'event_name' => $log->event?->name,
                    'type' => $log->time_out ? 'Time Out' : 'Time In',
                    'time' => optional($log->time_out ?? $log->time_in)->format('H:i'),
                    'date' => optional($log->created_at)->format('Y-m-d'),
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
