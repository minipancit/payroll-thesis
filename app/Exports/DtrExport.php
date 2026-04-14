<?php

namespace App\Exports;

use App\Models\DailyTimeRecord;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Carbon\Carbon;

class DtrExport implements FromCollection, WithHeadings, ShouldAutoSize, WithCustomStartCell
{
    protected $startDate;
    protected $endDate;
    protected $summary;
    protected $employeeSummaries;

    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate ?: today()->subDays(6)->toDateString();
        $this->endDate = $endDate ?: today()->toDateString();

        // Calculate employee-wise summaries
        $baseQuery = DailyTimeRecord::query()->dateRange($this->startDate, $this->endDate);
        $employees = (clone $baseQuery)->with('user')->get()->groupBy('user_id');

        $this->employeeSummaries = [];
        foreach ($employees as $userId => $records) {
            $user = $records->first()->user;
            if (!$user) continue;

            $this->employeeSummaries[] = [
                'user_id' => $user->id,
                'employee_id' => $user->employee_id,
                'name' => $user->name,
                'total_records' => $records->count(),
                'total_hours' => round($records->sum('total_hours'), 2),
                'absence_count' => $records->whereNull('actual_time_in')->count(),
                'late_count' => $records->where('late_minutes', '>', 0)->count(),
                'completed_count' => $records->whereNotNull('actual_time_in')->whereNotNull('actual_time_out')->count(),
                'overtime_count' => $records->where('overtime_minutes', '>', 0)->count(),
                'total_late_hours' => round($records->sum('late_minutes') / 60, 2),
                'total_undertime_hours' => round($records->sum('undertime_minutes') / 60, 2),
                'total_overtime_hours' => round($records->sum('overtime_minutes') / 60, 2),
                'records' => $records,
            ];
        }

        // Sort by employee name
        usort($this->employeeSummaries, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        // Calculate overall summary
        $this->summary = [
            'total_employees' => count($this->employeeSummaries),
            'overall_total_hours' => round(collect($this->employeeSummaries)->sum('total_hours'), 2),
            'overall_absence_count' => collect($this->employeeSummaries)->sum('absence_count'),
            'overall_total_late_hours' => round(collect($this->employeeSummaries)->sum('total_late_hours'), 2),
            'overall_total_undertime_hours' => round(collect($this->employeeSummaries)->sum('total_undertime_hours'), 2),
            'overall_total_overtime_hours' => round(collect($this->employeeSummaries)->sum('total_overtime_hours'), 2),
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $data = collect([]);

        // Add overall summary section
        $data->push(['OVERALL SUMMARY', '', '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Period', $this->startDate . ' to ' . $this->endDate, '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Total Employees', $this->summary['total_employees'], '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Total Hours (All)', $this->summary['overall_total_hours'], '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Total Absences (All)', $this->summary['overall_absence_count'], '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Total Late Hours (All)', $this->summary['overall_total_late_hours'], '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Total Undertime Hours (All)', $this->summary['overall_total_undertime_hours'], '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Total Overtime Hours (All)', $this->summary['overall_total_overtime_hours'], '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['', '', '', '', '', '', '', '', '', '', '', '', '', '']); // Empty row

        // Add employee-wise summaries and records
        foreach ($this->employeeSummaries as $employee) {
            // Employee summary header
            $data->push(['EMPLOYEE: ' . $employee['name'] . ' (' . $employee['employee_id'] . ')', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $data->push(['Total Hours', $employee['total_hours'], '', '', '', '', '', '', '', '', '', '', '', '']);
            $data->push(['Absences', $employee['absence_count'], '', '', '', '', '', '', '', '', '', '', '', '']);
            $data->push(['Late Hours', $employee['total_late_hours'], '', '', '', '', '', '', '', '', '', '', '', '']);
            $data->push(['Undertime Hours', $employee['total_undertime_hours'], '', '', '', '', '', '', '', '', '', '', '', '']);
            $data->push(['Overtime Hours', $employee['total_overtime_hours'], '', '', '', '', '', '', '', '', '', '', '', '']);
            $data->push(['Total Records', $employee['total_records'], '', '', '', '', '', '', '', '', '', '', '', '']);
            $data->push(['', '', '', '', '', '', '', '', '', '', '', '', '', '']); // Empty row

            // Employee records header
            $data->push(['RECORDS FOR ' . $employee['name'], '', '', '', '', '', '', '', '', '', '', '', '', '']);

            // Add individual records for this employee
            foreach ($employee['records'] as $record) {
                $data->push([
                    $record->log_date?->format('Y-m-d'),
                    $record->user?->employee_id,
                    $record->user?->name,
                    $record->event?->name,
                    optional($record->scheduled_time_in)->format('H:i'),
                    optional($record->scheduled_time_out)->format('H:i'),
                    optional($record->actual_time_in)->format('H:i'),
                    optional($record->actual_time_out)->format('H:i'),
                    $record->total_hours,
                    $record->late_minutes,
                    $record->overtime_minutes,
                    $record->undertime_minutes,
                    $record->status,
                    $record->remarks,
                ]);
            }

            // Empty row between employees
            $data->push(['', '', '', '', '', '', '', '', '', '', '', '', '', '']);
        }

        return $data;
    }

    /**
     * Define the headings for the Excel file
     */
    public function headings(): array
    {
        return [
            'Date',
            'Employee ID',
            'Employee Name',
            'Event',
            'Scheduled Time In',
            'Scheduled Time Out',
            'Actual Time In',
            'Actual Time Out',
            'Total Hours',
            'Late (minutes)',
            'Overtime (minutes)',
            'Undertime (minutes)',
            'Status',
            'Remarks',
        ];
    }

    /**
     * Start the headings at row 9 (after summary)
     */
    public function startCell(): string
    {
        return 'A9';
    }
}
