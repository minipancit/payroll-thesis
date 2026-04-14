<?php

namespace App\Exports;

use App\Models\DailyTimeRecord;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

class DtrExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate ?: today()->subDays(6)->toDateString();
        $this->endDate = $endDate ?: today()->toDateString();
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return DailyTimeRecord::with(['user', 'event'])
            ->dateRange($this->startDate, $this->endDate)
            ->orderByDesc('log_date')
            ->get();
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
     * Map the data for each row
     */
    public function map($dtr): array
    {
        return [
            $dtr->log_date?->format('Y-m-d'),
            $dtr->user?->employee_id,
            $dtr->user?->name,
            $dtr->event?->name,
            optional($dtr->scheduled_time_in)->format('H:i'),
            optional($dtr->scheduled_time_out)->format('H:i'),
            optional($dtr->actual_time_in)->format('H:i'),
            optional($dtr->actual_time_out)->format('H:i'),
            $dtr->total_hours,
            $dtr->late_minutes,
            $dtr->overtime_minutes,
            $dtr->undertime_minutes,
            $dtr->status,
            $dtr->remarks,
        ];
    }
}
