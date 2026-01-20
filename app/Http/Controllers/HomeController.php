<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\TimeLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $data = [];
        
        if ($user) {
            // Get ALL today's events (no time filtering)
            $events = Event::with('dailyTimeRecords')
                ->whereDate('event_date', Carbon::today())
                ->orderBy('start_time')
                ->get()
                ->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'name' => $event->name,
                        'latitude' => $event->latitude,
                        'longitude' => $event->longitude,
                        'address' => $event->address,
                        'formatted_date' => $event->formatted_date,
                        'formatted_time_range' => $event->formatted_time_range,
                        'status' => $event->status,
                        'is_active' => $event->is_active,
                        'is_past' => $event->is_past,
                        'is_future' => $event->is_future,
                    ];
                });
            
            // Get upcoming 3 events (including today's events)
            $upcomingEvents = Event::whereDate('event_date', '>=', Carbon::today())
                ->orderBy('event_date')
                ->orderBy('start_time')
                ->take(3)
                ->get()
                ->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'name' => $event->name,
                        'latitude' => $event->latitude,
                        'longitude' => $event->longitude,
                        'address' => $event->address,
                        'formatted_date' => $event->formatted_date,
                        'formatted_time_range' => $event->formatted_time_range,
                        'status' => $event->status,
                        'is_active' => $event->is_active,
                        'is_past' => $event->is_past,
                        'is_future' => $event->is_future,
                        'is_today' => $event->is_today,
                        'days_until' => $event->days_until,
                    ];
                });
            
            $activeLog = TimeLog::where('user_id', $user->id)
                ->whereNull('time_out')
                ->with('event')
                ->first();
            
            $data = [
                'events' => $events,
                'upcomingEvents' => $upcomingEvents,
                'activeLog' => $activeLog ? [
                    'id' => $activeLog->id,
                    'event_id' => $activeLog->event_id,
                    'time_in' => $activeLog->time_in,
                    'event' => [
                        'id' => $activeLog->event->id,
                        'name' => $activeLog->event->name,
                    ],
                ] : null,
            ];
        }
        
        return Inertia::render('Welcome', array_merge($data, []));
    }
}