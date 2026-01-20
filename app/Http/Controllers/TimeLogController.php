<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\TimeLog;
use App\Services\DTRService;
use App\Helpers\GeoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TimeLogController extends Controller
{
    protected $dtrService;
    
    public function __construct(DTRService $dtrService)
    {
        $this->dtrService = $dtrService;
    }
    
    public function timeIn(Request $request, $eventId)
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
        
        $event = Event::findOrFail($eventId);
        
        if (!$event->latitude || !$event->longitude) {
            return response()->json([
                'success' => false,
                'message' => 'Event location not set'
            ], 400);
        }
        
        $distance = GeoHelper::calculateDistance(
            $request->latitude,
            $request->longitude,
            $event->latitude,
            $event->longitude
        );
        
        $isWithinRadius = $distance <= 50;
        
        $userId = Auth::id();
        $now = Carbon::now();
        
        // Check for active time log
        $activeLog = TimeLog::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->whereNull('time_out')
            ->first();
        
        if ($activeLog) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active time log for this event'
            ], 400);
        }
        
        DB::beginTransaction();
        try {
            // Create time log
            $timeLog = TimeLog::create([
                'user_id' => $userId,
                'event_id' => $eventId,
                'user_latitude' => $request->latitude,
                'user_longitude' => $request->longitude,
                'is_within_radius' => $isWithinRadius,
                'distance_from_event' => $distance,
                'time_in' => $now,
            ]);
            
            // Process DTR
            $dtr = $this->dtrService->processTimeLog($timeLog, false);
            
            DB::commit();
            
            $response = [
                'success' => true,
                'within_radius' => $isWithinRadius,
                'distance' => round($distance, 2),
                'time_in' => $timeLog->time_in,
                'late_minutes' => $dtr->late_minutes,
            ];
            
            if ($isWithinRadius) {
                $response['message'] = 'Time in recorded successfully';
                if ($dtr->late_minutes > 0) {
                    $response['message'] .= '. You are ' . $dtr->late_minutes . ' minutes late.';
                }
            } else {
                $response['message'] = 'You are not within the 50m radius. Time in recorded but may not be valid.';
            }
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error recording time in: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function timeOut(Request $request, $eventId)
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
        
        $event = Event::findOrFail($eventId);
        
        if (!$event->latitude || !$event->longitude) {
            return response()->json([
                'success' => false,
                'message' => 'Event location not set'
            ], 400);
        }
        
        // Find active time log
        $timeLog = TimeLog::where('user_id', Auth::id())
            ->where('event_id', $eventId)
            ->whereNull('time_out')
            ->firstOrFail();
        
        $distance = GeoHelper::calculateDistance(
            $request->latitude,
            $request->longitude,
            $event->latitude,
            $event->longitude
        );
        
        DB::beginTransaction();
        try {
            // Update time log
            $timeLog->update([
                'time_out' => Carbon::now(),
                'distance_from_event_at_timeout' => $distance,
                'is_within_radius_at_timeout' => $distance <= 50,
            ]);
            
            // Process DTR for time-out
            $dtr = $this->dtrService->processTimeLog($timeLog, true);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'time_out' => $timeLog->time_out,
                'total_hours' => $dtr->total_hours,
                'overtime_minutes' => $dtr->overtime_minutes,
                'overtime_hours' => $dtr->overtime_hours,
                'undertime_minutes' => $dtr->undertime_minutes,
                'message' => 'Time out recorded successfully' . 
                    ($dtr->overtime_minutes >= 30 ? ' (' . $dtr->overtime_minutes . ' mins overtime)' : '')
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error recording time out: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function setSchedule(Request $request)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'date' => 'required|date',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'required|date_format:H:i|after:time_in',
        ]);
        
        $dtr = $this->dtrService->setSchedule(
            Auth::id(),
            $request->event_id,
            $request->date,
            $request->time_in,
            $request->time_out
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Schedule set successfully',
            'schedule' => [
                'date' => $dtr->log_date,
                'time_in' => Carbon::parse($dtr->scheduled_time_in)->format('H:i'),
                'time_out' => Carbon::parse($dtr->scheduled_time_out)->format('H:i'),
            ]
        ]);
    }
    
    public function getMyDTR(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        
        $userId = Auth::id();
        $query = DailyTimeRecord::with('event')
            ->where('user_id', $userId);
        
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('log_date', [$request->start_date, $request->end_date]);
        } else {
            // Default to current month
            $query->whereMonth('log_date', Carbon::now()->month)
                ->whereYear('log_date', Carbon::now()->year);
        }
        
        $dtrs = $query->orderBy('log_date', 'desc')
            ->paginate(20);
        
        // Calculate totals
        $totals = [
            'total_days' => $dtrs->count(),
            'total_overtime_minutes' => $dtrs->sum('overtime_minutes'),
            'total_late_minutes' => $dtrs->sum('late_minutes'),
            'total_undertime_minutes' => $dtrs->sum('undertime_minutes'),
        ];
        
        return response()->json([
            'success' => true,
            'dtrs' => $dtrs,
            'totals' => $totals
        ]);
    }
}