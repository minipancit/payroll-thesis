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

    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate ?: today()->subDays(6)->toDateString();
        $this->endDate = $endDate ?: today()->toDateString();

        // Calculate summary
        $baseQuery = DailyTimeRecord::query()->dateRange($this->startDate, $this->endDate);
        $this->summary = [
            'total_records' => (clone $baseQuery)->count(),
            'total_hours' => round((clone $baseQuery)->sum('total_hours'), 2),
            'absence_count' => (clone $baseQuery)->whereNull('actual_time_in')->count(),
            'total_late_hours' => round((clone $baseQuery)->sum('late_minutes') / 60, 2),
            'total_undertime_hours' => round((clone $baseQuery)->sum('undertime_minutes') / 60, 2),
            'total_overtime_hours' => round((clone $baseQuery)->sum('overtime_minutes') / 60, 2),
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $data = collect([]);

        // Add summary section
        $data->push(['SUMMARY', '', '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Period', $this->startDate . ' to ' . $this->endDate, '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Total Hours', $this->summary['total_hours'], '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Total Absences', $this->summary['absence_count'], '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Total Late Hours', $this->summary['total_late_hours'], '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Total Undertime Hours', $this->summary['total_undertime_hours'], '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['Total Overtime Hours', $this->summary['total_overtime_hours'], '', '', '', '', '', '', '', '', '', '', '', '']);
        $data->push(['', '', '', '', '', '', '', '', '', '', '', '', '', '']); // Empty row
        $data->push(['DETAILED RECORDS', '', '', '', '', '', '', '', '', '', '', '', '', '']); // Header for data

        // Add actual data
        $records = DailyTimeRecord::with(['user', 'event'])
            ->dateRange($this->startDate, $this->endDate)
            ->orderByDesc('log_date')
            ->get();

        foreach ($records as $record) {
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
