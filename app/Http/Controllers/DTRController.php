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

        // Group summary by employee
        $employeeSummaries = [];
        $employees = (clone $baseQuery)->with('user')->get()->groupBy('user_id');

        foreach ($employees as $userId => $records) {
            $user = $records->first()->user;
            if (!$user) continue;

            $employeeQuery = $records;

            $employeeSummaries[] = [
                'user_id' => $user->id,
                'employee_id' => $user->employee_id,
                'name' => $user->name,
                'total_records' => $employeeQuery->count(),
                'total_hours' => round($employeeQuery->sum('total_hours'), 2),
                'absence_count' => $employeeQuery->whereNull('actual_time_in')->count(),
                'late_count' => $employeeQuery->where('late_minutes', '>', 0)->count(),
                'completed_count' => $employeeQuery->whereNotNull('actual_time_in')->whereNotNull('actual_time_out')->count(),
                'overtime_count' => $employeeQuery->where('overtime_minutes', '>', 0)->count(),
                'total_late_hours' => round($employeeQuery->sum('late_minutes') / 60, 2),
                'total_undertime_hours' => round($employeeQuery->sum('undertime_minutes') / 60, 2),
                'total_overtime_hours' => round($employeeQuery->sum('overtime_minutes') / 60, 2),
            ];
        }

        // Sort by employee name
        usort($employeeSummaries, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        $summary = [
            'employees' => $employeeSummaries,
            'total_employees' => count($employeeSummaries),
            'overall_total_hours' => round(collect($employeeSummaries)->sum('total_hours'), 2),
            'overall_absence_count' => collect($employeeSummaries)->sum('absence_count'),
            'overall_total_late_hours' => round(collect($employeeSummaries)->sum('total_late_hours'), 2),
            'overall_total_undertime_hours' => round(collect($employeeSummaries)->sum('total_undertime_hours'), 2),
            'overall_total_overtime_hours' => round(collect($employeeSummaries)->sum('total_overtime_hours'), 2),
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
