<?php

namespace App\Http\Controllers;

use App\Exports\DtrExport;
use App\Models\DailyTimeRecord;
use App\Models\LoginAttempt;
use App\Services\DTRService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class DTRController extends Controller
{
    protected $dtrService;

    public function __construct(DTRService $dtrService)
    {
        $this->dtrService = $dtrService;
    }

    public function index(Request $request)
    {
        $startDate = $request->query('start') ? Carbon::parse($request->query('start'))->toDateString() : today()->subDays(6)->toDateString();
        $endDate = $request->query('end') ? Carbon::parse($request->query('end'))->toDateString() : today()->toDateString();

        // Process login attempts for all users/events in the date range
        $this->processLoginAttemptsForDateRange($startDate, $endDate);

        $baseQuery = DailyTimeRecord::query()->dateRange($startDate, $endDate);

        $dtrs = (clone $baseQuery)
            ->with(['user', 'event'])
            ->orderByDesc('log_date')
            ->paginate(20)
            ->withQueryString();

        $dtrs->getCollection()->transform(function (DailyTimeRecord $dtr) {
            return [
                'id' => $dtr->id,
                'user' => [
                    'id' => $dtr->user?->id,
                    'name' => $dtr->user?->name,
                    'employee_id' => $dtr->user?->employee_id,
                ],
                'event' => $dtr->event ? [
                    'id' => $dtr->event->id,
                    'name' => $dtr->event->name,
                    'formatted_date' => $dtr->event->formatted_date,
                    'formatted_time_range' => $dtr->event->formatted_time_range,
                ] : null,
                'log_date' => $dtr->log_date?->format('Y-m-d'),
                'scheduled_time_in' => optional($dtr->scheduled_time_in)->format('H:i'),
                'scheduled_time_out' => optional($dtr->scheduled_time_out)->format('H:i'),
                'actual_time_in' => optional($dtr->actual_time_in)->format('H:i'),
                'actual_time_out' => optional($dtr->actual_time_out)->format('H:i'),
                'total_hours' => $dtr->total_hours,
                'late_minutes' => $dtr->late_minutes,
                'late_formatted' => $dtr->late_formatted,
                'overtime_minutes' => $dtr->overtime_minutes,
                'overtime_formatted' => $dtr->overtime_formatted,
                'undertime_minutes' => $dtr->undertime_minutes,
                'undertime_formatted' => $dtr->undertime_formatted,
                'status' => $dtr->status,
                'remarks' => $dtr->remarks,
            ];
        });

        $summary = [
            'total_records' => (clone $baseQuery)->count(),
            'total_hours' => round((clone $baseQuery)->sum('total_hours'), 2),
            'late_count' => (clone $baseQuery)->late()->count(),
            'absence_count' => (clone $baseQuery)->whereNull('actual_time_in')->count(),
            'completed_count' => (clone $baseQuery)->completed()->count(),
            'overtime_count' => (clone $baseQuery)->withOvertime()->count(),
            'total_late_hours' => round((clone $baseQuery)->sum('late_minutes') / 60, 2),
            'total_undertime_hours' => round((clone $baseQuery)->sum('undertime_minutes') / 60, 2),
            'total_overtime_hours' => round((clone $baseQuery)->sum('overtime_minutes') / 60, 2),
        ];

        return Inertia::render('Admin/DTR/Index', [
            'dtrs' => $dtrs,
            'filters' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Process login attempts for all users/events in the given date range
     */
    private function processLoginAttemptsForDateRange(string $startDate, string $endDate): void
    {
        // Get all unique user/event/date combinations that have login attempts
        $loginAttempts = LoginAttempt::where('status', 'success')
            ->whereBetween('authenticated_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('user_id', 'event_id')
            ->selectRaw('DATE(authenticated_at) as log_date')
            ->groupBy('user_id', 'event_id', 'log_date')
            ->get();

        foreach ($loginAttempts as $attempt) {
            $this->dtrService->processDailyLoginAttempts(
                $attempt->user_id,
                $attempt->event_id,
                $attempt->log_date
            );
        }
    }

    public function update(Request $request, DailyTimeRecord $dtr)
    {
        return $dtr;
    }

    public function export(Request $request)
    {
        $startDate = $request->query('start') ? Carbon::parse($request->query('start'))->toDateString() : today()->subDays(6)->toDateString();
        $endDate = $request->query('end') ? Carbon::parse($request->query('end'))->toDateString() : today()->toDateString();

        $filename = 'dtr_' . $startDate . '_to_' . $endDate . '.xlsx';

        return Excel::download(new DtrExport($startDate, $endDate), $filename);
    }
}
